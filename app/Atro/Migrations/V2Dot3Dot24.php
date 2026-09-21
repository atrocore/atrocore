<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

/**
 * Local storage's file versions used to live flat under {storagePath}/version/{versionId},
 * regardless of the storage's syncFolders setting. They now live under a hidden
 * {storagePath}/.versions/{fileId|filePath}/{versionId} (fileId when syncFolders is on, the
 * file's own real relative path otherwise) - see Atro\Core\FileStorage\LocalStorage. This
 * relocates whatever already exists on disk to match, without touching file_version rows
 * (nothing there references the physical path - it's always recomputed from file/storage data).
 */
class V2Dot3Dot24 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-21 10:00:00');
    }

    public function up(): void
    {
        foreach ($this->fetchLocalStorages() as $storage) {
            $this->migrateStorage($storage);
        }
    }

    private function fetchLocalStorages(): array
    {
        try {
            return $this->getDbal()->createQueryBuilder()
                ->select('id, path, sync_folders')
                ->from('storage')
                ->where('type = :type')
                ->andWhere('deleted = :false')
                ->setParameter('type', 'local')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function migrateStorage(array $storage): void
    {
        $storagePath = trim((string)$storage['path'], '/');
        $oldVersionRoot = ($storagePath !== '' ? $storagePath . '/' : '') . 'version';

        if (!is_dir($oldVersionRoot)) {
            return;
        }

        foreach (scandir($oldVersionRoot) ?: [] as $versionId) {
            if ($versionId === '.' || $versionId === '..') {
                continue;
            }

            $oldDir = $oldVersionRoot . '/' . $versionId;
            if (is_dir($oldDir)) {
                $this->moveVersionFolder($storage, $storagePath, $versionId, $oldDir);
            }
        }

        // best-effort: only succeeds if every version folder underneath was actually moved out
        @rmdir($oldVersionRoot);
    }

    private function moveVersionFolder(array $storage, string $storagePath, string $versionId, string $oldDir): void
    {
        try {
            $versionRow = $this->getDbal()->createQueryBuilder()
                ->select('file_id')
                ->from('file_version')
                ->where('id = :id')
                ->setParameter('id', $versionId)
                ->fetchAssociative();

            // no matching version record left to key the new location off - leave it where it is
            // rather than guess
            if (empty($versionRow['file_id'])) {
                return;
            }

            $fileRow = $this->getDbal()->createQueryBuilder()
                ->select('id, path')
                ->from('file')
                ->where('id = :id')
                ->setParameter('id', $versionRow['file_id'])
                ->fetchAssociative();

            if (empty($fileRow)) {
                return;
            }

            $fileSegment = !empty($storage['sync_folders']) ? $fileRow['id'] : trim((string)$fileRow['path'], '/');
            if ($fileSegment === '') {
                return;
            }

            $newDir = ($storagePath !== '' ? $storagePath . '/' : '') . '.versions/' . $fileSegment . '/' . $versionId;

            if (is_dir($newDir)) {
                return; // already migrated (e.g. a re-run) - don't overwrite
            }

            if (!is_dir(dirname($newDir))) {
                mkdir(dirname($newDir), 0777, true);
            }

            rename($oldDir, $newDir);
        } catch (\Throwable $e) {
            // best-effort - one bad row must not abort the rest of the migration
        }
    }
}
