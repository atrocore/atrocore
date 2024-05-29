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
        return new \DateTime('2024-05-25 12:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE storage ADD folder_id VARCHAR(24) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_STORAGE_FOLDER_ID ON storage (folder_id, deleted)");
        $this->exec("CREATE UNIQUE INDEX IDX_STORAGE_UNIQUE_FOLDER ON storage (deleted, folder_id)");

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE storage ADD sync_folders BOOLEAN DEFAULT 'false' NOT NULL");
            $this->exec(
                "CREATE TABLE file_folder_linker (id VARCHAR(24) NOT NULL, name VARCHAR(255) NOT NULL, deleted BOOLEAN DEFAULT 'false', parent_id VARCHAR(255) NOT NULL, folder_id VARCHAR(255) DEFAULT NULL, file_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX IDX_FILE_FOLDER_LINKER_UNIQUE_ITEM ON file_folder_linker (deleted, parent_id, name)");
            $this->exec("CREATE INDEX IDX_FILE_FOLDER_LINKER_FOLDER_IDX ON file_folder_linker (parent_id, folder_id)");
            $this->exec("CREATE INDEX IDX_FILE_FOLDER_LINKER_FILE_IDX ON file_folder_linker (parent_id, file_id)");
            $this->exec("CREATE UNIQUE INDEX UNIQ_8B5ADE4E162CB942EB3B4E33 ON file_folder_linker (folder_id, deleted)");
            $this->exec("CREATE UNIQUE INDEX UNIQ_8B5ADE4E93CB796CEB3B4E33 ON file_folder_linker (file_id, deleted)");
        } else {
            $this->exec("ALTER TABLE storage ADD sync_folders TINYINT(1) DEFAULT '0' NOT NULL");
            $this->exec(
                "CREATE TABLE file_folder_linker (id VARCHAR(24) NOT NULL, name VARCHAR(255) NOT NULL, deleted TINYINT(1) DEFAULT '0', parent_id VARCHAR(255) NOT NULL, folder_id VARCHAR(255) DEFAULT NULL, file_id VARCHAR(255) DEFAULT NULL, UNIQUE INDEX IDX_FILE_FOLDER_LINKER_UNIQUE_ITEM (deleted, parent_id, name), INDEX IDX_FILE_FOLDER_LINKER_FOLDER_IDX (parent_id, folder_id), INDEX IDX_FILE_FOLDER_LINKER_FILE_IDX (parent_id, file_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec("CREATE UNIQUE INDEX UNIQ_8B5ADE4E162CB942EB3B4E33 ON file_folder_linker (folder_id, deleted)");
            $this->exec("CREATE UNIQUE INDEX UNIQ_8B5ADE4E93CB796CEB3B4E33 ON file_folder_linker (file_id, deleted)");
        }

        $this->exec("ALTER TABLE folder ADD storage_id VARCHAR(24) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_FOLDER_STORAGE_ID ON folder (storage_id, deleted)");

        $this->createFoldersItems();
        $this->createFilesItems();

        V1Dot10Dot0::createDefaultStorage($this->getConnection());

        $this->getConnection()->createQueryBuilder()
            ->update('storage')
            ->set('folder_id', ':empty')
            ->where('id=:storageId')
            ->setParameter('empty', '')
            ->setParameter('storageId', 'a_base')
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->update('storage')
            ->set('is_active', ':false')
            ->where('id!=:storageId')
            ->setParameter('storageId', 'a_base')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->update('folder')
            ->set('storage_id', ':storageId')
            ->where('deleted=:false')
            ->setParameter('storageId', 'a_base')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

//        $this->exec("DROP TABLE folder_storage");

        $this->updateComposer('atrocore/core', '^1.11.0');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    public function createFoldersItems(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->delete('file_folder_linker')
            ->where('folder_id IS NOT NULL')
            ->executeQuery();

        $records = $this->getConnection()->createQueryBuilder()
            ->select('f.*, h.parent_id')
            ->from('folder', 'f')
            ->leftJoin('f', 'folder_hierarchy', 'h', 'f.id=h.entity_id')
            ->where('f.deleted=:false')
            ->andWhere('f.deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $duplicates = [];

        foreach ($records as $record) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->insert('file_folder_linker')
                    ->setValue('id', ':id')
                    ->setValue('name', ':name')
                    ->setValue('parent_id', ':parentId')
                    ->setValue('folder_id', ':folderId')
                    ->setParameter('id', Util::generateId())
                    ->setParameter('name', (string)$record['name'])
                    ->setParameter('parentId', (string)$record['parent_id'])
                    ->setParameter('folderId', (string)$record['id'])
                    ->executeQuery();
            } catch (UniqueConstraintViolationException $e) {
                $duplicates["{$record['parent_id']}_{$record['name']}"][] = $record;
            }
        }

        foreach ($duplicates as $rows) {
            foreach ($rows as $k => $row) {
                $newName = $row['name'] . '(' . ($k + 1) . ')';
                $this->getConnection()->createQueryBuilder()
                    ->update('folder')
                    ->set('name', ':name')
                    ->where('id=:id')
                    ->setParameter('id', $row['id'])
                    ->setParameter('name', $newName)
                    ->executeQuery();
            }
        }

        if (!empty($duplicates)) {
            $this->createFoldersItems();
        }
    }

    public function createFilesItems(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->delete('file_folder_linker')
            ->where('file_id IS NOT NULL')
            ->executeQuery();

        $records = $this->getConnection()->createQueryBuilder()
            ->select('f.*, s.path as storage_path')
            ->from('file', 'f')
            ->innerJoin('f', 'storage', 's', 'f.storage_id=s.id')
            ->where('f.deleted=:false')
            ->andWhere('s.deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($records as $record) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->insert('file_folder_linker')
                    ->setValue('id', ':id')
                    ->setValue('name', ':name')
                    ->setValue('parent_id', ':parentId')
                    ->setValue('file_id', ':fileId')
                    ->setParameter('id', Util::generateId())
                    ->setParameter('name', (string)$record['name'])
                    ->setParameter('parentId', (string)$record['folder_id'])
                    ->setParameter('fileId', (string)$record['id'])
                    ->executeQuery();
            } catch (UniqueConstraintViolationException $e) {
                $duplicates["{$record['folder_id']}_{$record['name']}"][] = $record;
            }
        }

        foreach ($duplicates as $rows) {
            foreach ($rows as $k => $row) {
                $parts = explode('.', $record['name']);
                $ext = array_pop($parts);
                $newName = implode('.', $parts) . '(' . ($k + 1) . ').' . $ext;

                $this->getConnection()->createQueryBuilder()
                    ->update('file')
                    ->set('name', ':name')
                    ->where('id=:id')
                    ->setParameter('id', $row['id'])
                    ->setParameter('name', $newName)
                    ->executeQuery();

                $filePath = $row['storage_path'] . DIRECTORY_SEPARATOR . $row['path'];

                @rename($filePath . DIRECTORY_SEPARATOR . $record['name'], $filePath . DIRECTORY_SEPARATOR . $newName);
            }
        }

        if (!empty($duplicates)) {
            $this->createFilesItems();
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
