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
use Doctrine\DBAL\ParameterType;

class V1Dot10Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-03-21');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            //@todo  prepare DB schema CREATE NEW TABLES
        } else {
            $this->exec(
                "CREATE TABLE file (id VARCHAR(24) NOT NULL, name VARCHAR(255) NOT NULL, deleted TINYINT(1) DEFAULT '0', description LONGTEXT DEFAULT NULL, private TINYINT(1) DEFAULT '0' NOT NULL, mime_type VARCHAR(255) DEFAULT NULL, file_size INT DEFAULT NULL, file_mtime DATETIME DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, path VARCHAR(255) DEFAULT NULL, thumbnails_path LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, storage_id VARCHAR(24) DEFAULT NULL, folder_id VARCHAR(24) DEFAULT NULL, type_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX IDX_FILE_UNIQUE_FILE (deleted, name, path, storage_id), INDEX IDX_FILE_HASH (hash, deleted), INDEX IDX_FILE_STORAGE_ID (storage_id, deleted), INDEX IDX_FILE_FOLDER_ID (folder_id, deleted), INDEX IDX_FILE_TYPE_ID (type_id, deleted), INDEX IDX_FILE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_FILE_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE file_type (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', assign_automatically TINYINT(1) DEFAULT '0' NOT NULL, sort_order INT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, INDEX IDX_FILE_TYPE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_FILE_TYPE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_FILE_TYPE_NAME (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE folder (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', description LONGTEXT DEFAULT NULL, sort_order INT DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX UNIQ_ECA209CD77153098EB3B4E33 (code, deleted), INDEX IDX_FOLDER_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_FOLDER_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_FOLDER_NAME (name, deleted), INDEX IDX_FOLDER_CREATED_AT (created_at, deleted), INDEX IDX_FOLDER_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE storage (id VARCHAR(24) NOT NULL, name VARCHAR(255) NOT NULL, deleted TINYINT(1) DEFAULT '0', type VARCHAR(255) DEFAULT 'fileSystem', path VARCHAR(255) DEFAULT 'upload/files', priority INT DEFAULT 10 NOT NULL, created_at DATETIME DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, created_by_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX UNIQ_547A1B34B548B0FEB3B4E33 (path, deleted), INDEX IDX_STORAGE_CREATED_BY_ID (created_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE folder_hierarchy (id VARCHAR(24) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, hierarchy_sort_order INT DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, parent_id VARCHAR(24) DEFAULT NULL, entity_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX IDX_FOLDER_HIERARCHY_UNIQUE_RELATION (deleted, parent_id, entity_id), INDEX IDX_FOLDER_HIERARCHY_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_FOLDER_HIERARCHY_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_FOLDER_HIERARCHY_PARENT_ID (parent_id, deleted), INDEX IDX_FOLDER_HIERARCHY_ENTITY_ID (entity_id, deleted), INDEX IDX_FOLDER_HIERARCHY_CREATED_AT (created_at, deleted), INDEX IDX_FOLDER_HIERARCHY_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE folder_storage (id VARCHAR(24) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, folder_id VARCHAR(24) DEFAULT NULL, storage_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX IDX_FOLDER_STORAGE_UNIQUE_RELATION (deleted, folder_id, storage_id), INDEX IDX_FOLDER_STORAGE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_FOLDER_STORAGE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_FOLDER_STORAGE_FOLDER_ID (folder_id, deleted), INDEX IDX_FOLDER_STORAGE_STORAGE_ID (storage_id, deleted), INDEX IDX_FOLDER_STORAGE_CREATED_AT (created_at, deleted), INDEX IDX_FOLDER_STORAGE_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );

            $this->exec("ALTER TABLE sharing CHANGE entity_type entity_type VARCHAR(255) DEFAULT 'File'");
            $this->exec("DROP INDEX IDX_VALIDATION_RULE_ASSET_TYPE_ID ON validation_rule");
            $this->exec("ALTER TABLE validation_rule CHANGE asset_type_id file_type_id VARCHAR(24) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_VALIDATION_RULE_FILE_TYPE_ID ON validation_rule (file_type_id, deleted)");
        }

        self::createDefaultStorage($this->getConnection());

        $this->migrateAssetCategories();
        $this->migrateAssetTypes();
        $this->migrateAssets();

        if ($this->isPgSQL()) {
            //@todo prepare DB schema DELETE OLD TABLES
        } else {
//            $this->exec("DROP TABLE asset_asset");
//            $this->exec("DROP TABLE asset_category");
//            $this->exec("DROP TABLE asset_category_asset");
//            $this->exec("DROP TABLE asset_category_hierarchy");
//            $this->exec("DROP TABLE asset_hierarchy");
//            $this->exec("DROP TABLE asset_metadata");
//            $this->exec("DROP TABLE asset_type");
//            $this->exec("DROP TABLE library");
//            $this->exec("DROP TABLE library_asset_category");
//            $this->exec("DROP TABLE asset");
//            $this->exec("DROP TABLE attachment");
        }

        $this->getConfig()->set('globalSearchEntityList', array_values(array_unique(array_merge($this->getConfig()->get('globalSearchEntityList', []), ['File', 'Folder']))));
        $this->getConfig()->set('tabList', array_values(array_unique(array_merge($this->getConfig()->get('tabList', []), ['File', 'Folder']))));
        $this->getConfig()->set('twoLevelTabList', array_values(array_unique(array_merge($this->getConfig()->get('twoLevelTabList', []), ['File', 'Folder']))));
        $this->getConfig()->set('quickCreateList', array_values(array_unique(array_merge($this->getConfig()->get('quickCreateList', []), ['File', 'Folder']))));
        $this->getConfig()->remove('whitelistedExtensions');
        $this->getConfig()->save();

        $this->updateComposer('atrocore/core', '^1.10.0');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    protected function migrateAssets(): void
    {
        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('a.*, ass.type as asset_type, ass.description')
                ->from($this->getConnection()->quoteIdentifier('attachment'), 'a')
                ->leftJoin('a', $this->getConnection()->quoteIdentifier('asset'), 'ass', 'ass.file_id=a.id AND ass.deleted=:false')
                ->where('a.deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $v) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->delete('attachment')
                    ->where('id = :id')
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            } catch (\Throwable $e) {
            }

            $fileName = trim($this->getConfig()->get('filesPath', 'upload/files'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $v['storage_file_path'];
            $fileName .= DIRECTORY_SEPARATOR . $v['name'];

            if (!file_exists($fileName)) {
                continue;
            }

            $fileMtime = gmdate("Y-m-d H:i:s", filemtime($fileName));
            $hash = md5_file($fileName);
            $typeId = null;

            $assetTypes = @json_decode($v['asset_type'], true);
            if (!empty($assetTypes)) {
                $typeId = array_shift($assetTypes);
            }

            $qb = $this->getConnection()->createQueryBuilder()
                ->insert('file')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('description', ':description')
                ->setValue('mime_type', ':mimeType')
                ->setValue('private', ':private')
                ->setValue('file_size', ':fileSize')
                ->setValue('file_mtime', ':fileMtime')
                ->setValue('hash', ':hash')
                ->setValue('path', ':path')
                ->setValue('thumbnails_path', ':thumbnailsPath')
                ->setValue('storage_id', ':storageId')
                ->setValue('type_id', ':typeId')
                ->setValue('created_at', ':createdAt')
                ->setValue('modified_at', ':modifiedAt')
                ->setValue('created_by_id', ':createdById')
                ->setValue('modified_by_id', ':modifiedById')
                ->setParameter('id', $v['id'])
                ->setParameter('name', $v['name'])
                ->setParameter('description', $v['description'])
                ->setParameter('mimeType', $v['type'])
                ->setParameter('private', !empty($v['private']), ParameterType::BOOLEAN)
                ->setParameter('fileSize', $v['size'])
                ->setParameter('fileMtime', $fileMtime)
                ->setParameter('hash', $hash)
                ->setParameter('path', $v['storage_file_path'])
                ->setParameter('thumbnailsPath', $v['storage_thumb_path'])
                ->setParameter('storageId', 'a_base')
                ->setParameter('typeId', $typeId)
                ->setParameter('createdAt', $v['created_at'])
                ->setParameter('modifiedAt', $v['modified_at'])
                ->setParameter('createdById', $v['created_by_id'])
                ->setParameter('modifiedById', $v['modified_by_id']);
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
        }

        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('aca.*, a.file_id')
                ->from('asset_category_asset', 'aca')
                ->join('aca', 'asset', 'a', 'a.id=aca.asset_id')
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $v) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->update('file')
                    ->set('folder_id', ':folderId')
                    ->where('id = :id')
                    ->setParameter('id', $v['file_id'])
                    ->setParameter('folderId', $v['asset_category_id'])
                    ->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }

    protected function migrateAssetCategories(): void
    {
        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('asset_category')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $v) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->delete('asset_category')
                    ->where('id = :id')
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            } catch (\Throwable $e) {
            }

            $qb = $this->getConnection()->createQueryBuilder()
                ->insert('folder')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('description', ':description')
                ->setValue('sort_order', ':sortOrder')
                ->setValue('code', ':code')
                ->setValue('created_at', ':createdAt')
                ->setValue('modified_at', ':modifiedAt')
                ->setValue('created_by_id', ':createdById')
                ->setValue('modified_by_id', ':modifiedById')
                ->setParameter('id', $v['id'])
                ->setParameter('name', $v['name'])
                ->setParameter('description', $v['description'])
                ->setParameter('sortOrder', $v['sort_order'])
                ->setParameter('code', $v['code'])
                ->setParameter('createdAt', $v['created_at'])
                ->setParameter('modifiedAt', $v['modified_at'])
                ->setParameter('createdById', $v['created_by_id'])
                ->setParameter('modifiedById', $v['modified_by_id']);
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
        }

        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('asset_category_hierarchy')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $v) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->delete('asset_category_hierarchy')
                    ->where('id = :id')
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            } catch (\Throwable $e) {
            }

            $qb = $this->getConnection()->createQueryBuilder()
                ->insert('folder_hierarchy')
                ->setValue('id', ':id')
                ->setValue('created_at', ':createdAt')
                ->setValue('modified_at', ':modifiedAt')
                ->setValue('created_by_id', ':createdById')
                ->setValue('modified_by_id', ':modifiedById')
                ->setValue('hierarchy_sort_order', ':hierarchySortOrder')
                ->setValue('parent_id', ':parentId')
                ->setValue('entity_id', ':entityId')
                ->setParameter('id', $v['id'])
                ->setParameter('createdAt', $v['created_at'])
                ->setParameter('modifiedAt', $v['modified_at'])
                ->setParameter('createdById', $v['created_by_id'])
                ->setParameter('modifiedById', $v['modified_by_id'])
                ->setParameter('hierarchySortOrder', $v['hierarchy_sort_order'])
                ->setParameter('parentId', $v['parent_id'])
                ->setParameter('entityId', $v['entity_id']);
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }

    protected function migrateAssetTypes(): void
    {
        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('asset_type')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $v) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->delete('asset_type')
                    ->where('id = :id')
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            } catch (\Throwable $e) {
            }

            $qb = $this->getConnection()->createQueryBuilder()
                ->insert('file_type')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('assign_automatically', ':assignAutomatically')
                ->setValue('sort_order', ':sortOrder')
                ->setValue('created_by_id', ':createdById')
                ->setValue('modified_by_id', ':modifiedById')
                ->setParameter('id', $v['id'])
                ->setParameter('name', $v['name'])
                ->setParameter('assignAutomatically', !empty($v['assign_automatically']), ParameterType::BOOLEAN)
                ->setParameter('sortOrder', $v['sort_order'])
                ->setParameter('createdById', $v['created_by_id'])
                ->setParameter('modifiedById', $v['modified_by_id']);
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('validation_rule')
                ->set('file_type_id', 'asset_type_id')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        } catch (\Throwable $e) {
        }

        self::createDefaultFileTypes($this->getConnection());
    }

    public static function createDefaultStorage(Connection $conn): void
    {
        $qb = $conn->createQueryBuilder()
            ->insert('storage')
            ->setValue('id', ':id')
            ->setValue('name', ':name')
            ->setValue('type', ':type')
            ->setValue('path', ':path')
            ->setValue('is_active', ':true')
            ->setValue('created_by_id', ':system')
            ->setParameter('id', 'a_base')
            ->setParameter('name', 'Base')
            ->setParameter('type', 'local')
            ->setParameter('path', 'upload/files')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('system', 'system');
        try {
            $qb->executeQuery();
        } catch (\Throwable $e) {
        }
    }

    public static function createDefaultFileTypes(Connection $conn): void
    {
        $defaults = [
            [
                'id'                  => 'a_document',
                'name'                => 'Document',
                'assignAutomatically' => true,
                'extensions'          => ['docx', 'doc', 'odt', 'rtf', 'tex', 'txt', 'pdf']
            ],
            [
                'id'                  => 'a_spreadsheet',
                'name'                => 'Spreadsheet',
                'assignAutomatically' => true,
                'extensions'          => ['xlsx', 'xls', 'ods', 'csv', 'tsv']
            ],
            [
                'id'                  => 'a_image',
                'name'                => 'Image',
                'assignAutomatically' => true,
                'extensions'          => ['jpg', 'jpeg', 'gif', 'tiff', 'png', 'bmp']
            ],
            [
                'id'                  => 'a_audio',
                'name'                => 'Audio',
                'assignAutomatically' => true,
                'extensions'          => ['mp3', 'wav', 'aac', 'flac', 'ogg']
            ],
            [
                'id'                  => 'a_video',
                'name'                => 'Video',
                'assignAutomatically' => true,
                'extensions'          => ['mp4', 'avi', 'mkv', 'wmv', 'mov']
            ],
            [
                'id'                  => 'a_archive',
                'name'                => 'Archive',
                'assignAutomatically' => true,
                'extensions'          => ['zip', 'rar', '7z']
            ],
            [
                'id'                  => 'a_graphics',
                'name'                => 'Graphics',
                'assignAutomatically' => true,
                'extensions'          => ['ai', 'svg']
            ],
            [
                'id'                  => 'a_presentation',
                'name'                => 'Presentation',
                'assignAutomatically' => true,
                'extensions'          => ['pptx', 'ppt', 'ppsx', 'odp', 'key']
            ],
        ];

        foreach ($defaults as $k => $default) {
            $qb = $conn->createQueryBuilder()
                ->insert('file_type')
                ->setValue('id', ':id')
                ->setValue('name', ':name')
                ->setValue('sort_order', ':sortOrder')
                ->setValue('assign_automatically', ':assignAutomatically')
                ->setValue('created_by_id', ':system')
                ->setValue('modified_by_id', ':system')
                ->setParameter('id', $default['id'])
                ->setParameter('name', $default['name'])
                ->setParameter('sortOrder', $k + 100)
                ->setParameter('assignAutomatically', $default['assignAutomatically'], ParameterType::BOOLEAN)
                ->setParameter('system', 'system');
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }

            $qb1 = $conn->createQueryBuilder()
                ->insert('validation_rule')
                ->setValue('id', ':id')
                ->setValue('name', ':type')
                ->setValue('type', ':type')
                ->setValue('is_active', ':true')
                ->setValue('extension', ':extension')
                ->setValue('created_by_id', ':system')
                ->setValue('modified_by_id', ':system')
                ->setValue('file_type_id', ':fileTypeId')
                ->setParameter('id', "v_{$default['id']}")
                ->setParameter('type', 'Extension')
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('extension', json_encode($default['extensions']))
                ->setParameter('system', 'system')
                ->setParameter('fileTypeId', $default['id']);

            try {
                $qb1->executeQuery();
            } catch (\Throwable $e) {
            }
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
