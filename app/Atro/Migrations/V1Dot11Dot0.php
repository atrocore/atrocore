<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot11Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-05-21 12:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE storage ADD folder_id VARCHAR(24) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_STORAGE_FOLDER_ID ON storage (folder_id, deleted)");

        $this->exec("ALTER TABLE folder ADD hash VARCHAR(255) DEFAULT NULL");
        $this->exec("CREATE UNIQUE INDEX UNIQ_ECA209CDD1B862B8EB3B4E33 ON folder (hash, deleted)");

        self::updateFoldersHash($this->getConnection());

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE storage ADD sync_folders BOOLEAN DEFAULT 'false' NOT NULL");
        } else {
            $this->exec("ALTER TABLE storage ADD sync_folders TINYINT(1) DEFAULT '0' NOT NULL");
        }

        try {
            $records = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('folder_storage')
                ->where('deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $records = [];
        }

        foreach ($records as $record) {
            $this->getConnection()->createQueryBuilder()
                ->update('storage')
                ->set('folder_id', ':folderId')
                ->where('id=:storageId')
                ->setParameter('folderId', $record['folder_id'])
                ->setParameter('storageId', $record['storage_id'])
                ->executeQuery();
        }

        $this->exec("DROP TABLE folder_storage");

        $this->updateComposer('atrocore/core', '^1.11.0');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    public static function updateFoldersHash(Connection $conn): void
    {
        $records = $conn->createQueryBuilder()
            ->select('f.*, h.parent_id')
            ->from('folder', 'f')
            ->leftJoin('f', 'folder_hierarchy', 'h', 'f.id=h.entity_id')
            ->where('f.deleted=:false')
            ->andWhere('f.deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($records as $record) {
            try {
                self::updateFolderHash((string)$record['id'], (string)$record['name'], (string)$record['parent_id'], $conn);
            } catch (UniqueConstraintViolationException $e) {
                $record['name'] .= ' ' . Util::generateId();
                self::updateFolderHash((string)$record['id'], (string)$record['name'], (string)$record['parent_id'], $conn);
            } catch (\Throwable $e) {
            }
        }
    }

    public static function updateFolderHash(string $id, string $name, string $parentId, Connection $conn): void
    {
        $hash = md5("{$name}_{$parentId}");
        $conn->createQueryBuilder()
            ->update('folder')
            ->set('hash', ':hash')
            ->set('name', ':name')
            ->where('id=:id')
            ->setParameter('id', $id)
            ->setParameter('name', $name)
            ->setParameter('hash', $hash)
            ->executeQuery();
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
