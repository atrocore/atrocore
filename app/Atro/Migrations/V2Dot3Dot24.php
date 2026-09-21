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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V2Dot3Dot24 extends Base
{
    protected const array ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'gif',
        'png',
        'webp',
        'svg',
        'avif',
        'jfif',
    ];

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-21 18:00:00');
    }

    public function up(): void
    {
        // migrate files based avatars
        $this->migrateFileBasedAvatars();

        // drop avatar column
        $this->dropAvatarColumn();
    }

    protected function migrateFileBasedAvatars(): void
    {
        $rows = $this->getDbal()->createQueryBuilder()
            ->select('u.id', 'f.name', 'f.file_size', 'f.path', 's.path AS storage_path')
            ->from('user', 'u')
            ->join('u', 'file', 'f', 'u.avatar_id = f.id AND f.deleted = :false')
            ->join('f', 'storage', 's', 's.id = f.storage_id AND s.deleted = :false')
            ->where('avatar_id IS NOT NULL')
            ->andWhere("avatar_id != ''")
            ->andWhere('s.type = :type')
            ->andWhere('u.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('type', 'local')
            ->fetchAllAssociative();

        if (!empty($rows)) {
            $maxAllowedSize = $this->convertToBytes((string)ini_get('upload_max_filesize'));

            foreach ($rows as $row) {
                $extension = strtolower((string)pathinfo($row['name'], PATHINFO_EXTENSION));

                if (in_array($extension, self::ALLOWED_EXTENSIONS, true) && $row['file_size'] <= $maxAllowedSize) {
                    $fullPath = $row['storage_path'] . DIRECTORY_SEPARATOR . $row['path'] . DIRECTORY_SEPARATOR . $row['name'];

                    if (file_exists($fullPath)) {
                        $dir = 'data/upload/avatars/' . $row['id'];

                        if (!is_dir($dir)) {
                            mkdir($dir, 0777, true);
                        }

                        copy($fullPath, $dir . DIRECTORY_SEPARATOR . $row['name']);
                    }
                }
            }
        }
    }

    protected function dropAvatarColumn(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $table = $toSchema->getTable('user');
        if ($table->hasColumn('avatar_id')) {
            $this->dropColumn($toSchema, 'user', 'avatar_id');
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    protected function convertToBytes(string $size): int
    {
        $suffix = substr($size, -1);
        $value = (int)substr($size, 0, -1);

        switch (strtoupper($suffix)) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
                break;
        }

        return $value;
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable) {
        }
    }
}
