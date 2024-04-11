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
use Espo\Core\Utils\Util;

class V1Dot10Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-10 00:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec(
                "CREATE TABLE file (id VARCHAR(24) NOT NULL, name VARCHAR(255) NOT NULL, deleted BOOLEAN DEFAULT 'false', hidden BOOLEAN DEFAULT 'false', description TEXT DEFAULT NULL, private BOOLEAN DEFAULT 'false' NOT NULL, mime_type VARCHAR(255) DEFAULT NULL, file_size INT DEFAULT NULL, file_mtime TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, path VARCHAR(255) DEFAULT NULL, thumbnails_path TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, storage_id VARCHAR(24) DEFAULT NULL, folder_id VARCHAR(24) DEFAULT NULL, type_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX IDX_FILE_UNIQUE_FILE ON file (deleted, name, path, storage_id)");
            $this->exec("CREATE INDEX IDX_FILE_HASH ON file (hash, deleted)");
            $this->exec("CREATE INDEX IDX_FILE_STORAGE_ID ON file (storage_id, deleted)");
            $this->exec("CREATE INDEX IDX_FILE_FOLDER_ID ON file (folder_id, deleted)");
            $this->exec("CREATE INDEX IDX_FILE_TYPE_ID ON file (type_id, deleted)");
            $this->exec("CREATE INDEX IDX_FILE_CREATED_BY_ID ON file (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_FILE_MODIFIED_BY_ID ON file (modified_by_id, deleted)");

            $this->exec(
                "CREATE TABLE file_type (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', assign_automatically BOOLEAN DEFAULT 'false' NOT NULL, sort_order INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE INDEX IDX_FILE_TYPE_CREATED_BY_ID ON file_type (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_FILE_TYPE_MODIFIED_BY_ID ON file_type (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_FILE_TYPE_NAME ON file_type (name, deleted)");

            $this->exec(
                "CREATE TABLE folder (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', hidden BOOLEAN DEFAULT 'false', description TEXT DEFAULT NULL, sort_order INT DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX UNIQ_ECA209CD77153098EB3B4E33 ON folder (code, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_CREATED_BY_ID ON folder (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_MODIFIED_BY_ID ON folder (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_NAME ON folder (name, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_CREATED_AT ON folder (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_MODIFIED_AT ON folder (modified_at, deleted)");

            $this->exec(
                "CREATE TABLE storage (id VARCHAR(24) NOT NULL, name VARCHAR(255) NOT NULL, deleted BOOLEAN DEFAULT 'false', type VARCHAR(255) DEFAULT 'local', path VARCHAR(255) DEFAULT 'upload/files', priority INT DEFAULT 10 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, created_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX UNIQ_547A1B34B548B0FEB3B4E33 ON storage (path, deleted)");
            $this->exec("CREATE INDEX IDX_STORAGE_CREATED_BY_ID ON storage (created_by_id, deleted)");

            $this->exec(
                "CREATE TABLE folder_hierarchy (id VARCHAR(24) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, hierarchy_sort_order INT DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, parent_id VARCHAR(24) DEFAULT NULL, entity_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX IDX_FOLDER_HIERARCHY_UNIQUE_RELATION ON folder_hierarchy (deleted, parent_id, entity_id)");
            $this->exec("CREATE INDEX IDX_FOLDER_HIERARCHY_CREATED_BY_ID ON folder_hierarchy (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_HIERARCHY_MODIFIED_BY_ID ON folder_hierarchy (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_HIERARCHY_PARENT_ID ON folder_hierarchy (parent_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_HIERARCHY_ENTITY_ID ON folder_hierarchy (entity_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_HIERARCHY_CREATED_AT ON folder_hierarchy (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_HIERARCHY_MODIFIED_AT ON folder_hierarchy (modified_at, deleted)");

            $this->exec(
                "CREATE TABLE folder_storage (id VARCHAR(24) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, folder_id VARCHAR(24) DEFAULT NULL, storage_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX IDX_FOLDER_STORAGE_UNIQUE_RELATION ON folder_storage (deleted, folder_id, storage_id)");
            $this->exec("CREATE INDEX IDX_FOLDER_STORAGE_CREATED_BY_ID ON folder_storage (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_STORAGE_MODIFIED_BY_ID ON folder_storage (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_STORAGE_FOLDER_ID ON folder_storage (folder_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_STORAGE_STORAGE_ID ON folder_storage (storage_id, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_STORAGE_CREATED_AT ON folder_storage (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_FOLDER_STORAGE_MODIFIED_AT ON folder_storage (modified_at, deleted)");

            $this->exec("ALTER TABLE sharing ALTER entity_type SET DEFAULT 'File'");
            $this->exec("DROP INDEX idx_validation_rule_asset_type_id");
            $this->exec("ALTER TABLE validation_rule RENAME COLUMN asset_type_id TO file_type_id");
            $this->exec("CREATE INDEX IDX_VALIDATION_RULE_FILE_TYPE_ID ON validation_rule (file_type_id, deleted)");
        } else {
            $this->exec(
                "CREATE TABLE file (id VARCHAR(24) NOT NULL, name VARCHAR(255) NOT NULL, deleted TINYINT(1) DEFAULT '0', hidden TINYINT(1) DEFAULT '0', description LONGTEXT DEFAULT NULL, private TINYINT(1) DEFAULT '0' NOT NULL, mime_type VARCHAR(255) DEFAULT NULL, file_size INT DEFAULT NULL, file_mtime DATETIME DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, path VARCHAR(255) DEFAULT NULL, thumbnails_path LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, storage_id VARCHAR(24) DEFAULT NULL, folder_id VARCHAR(24) DEFAULT NULL, type_id VARCHAR(24) DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX IDX_FILE_UNIQUE_FILE (deleted, name, path, storage_id), INDEX IDX_FILE_HASH (hash, deleted), INDEX IDX_FILE_STORAGE_ID (storage_id, deleted), INDEX IDX_FILE_FOLDER_ID (folder_id, deleted), INDEX IDX_FILE_TYPE_ID (type_id, deleted), INDEX IDX_FILE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_FILE_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE file_type (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', assign_automatically TINYINT(1) DEFAULT '0' NOT NULL, sort_order INT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, INDEX IDX_FILE_TYPE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_FILE_TYPE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_FILE_TYPE_NAME (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE folder (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', hidden TINYINT(1) DEFAULT '0', description LONGTEXT DEFAULT NULL, sort_order INT DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX UNIQ_ECA209CD77153098EB3B4E33 (code, deleted), INDEX IDX_FOLDER_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_FOLDER_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_FOLDER_NAME (name, deleted), INDEX IDX_FOLDER_CREATED_AT (created_at, deleted), INDEX IDX_FOLDER_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE storage (id VARCHAR(24) NOT NULL, name VARCHAR(255) NOT NULL, deleted TINYINT(1) DEFAULT '0', type VARCHAR(255) DEFAULT 'local', path VARCHAR(255) DEFAULT 'upload/files', priority INT DEFAULT 10 NOT NULL, created_at DATETIME DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, created_by_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX UNIQ_547A1B34B548B0FEB3B4E33 (path, deleted), INDEX IDX_STORAGE_CREATED_BY_ID (created_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
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

        $this->exec("ALTER TABLE file_type RENAME COLUMN sort_order to priority");
        $this->exec("ALTER TABLE file_type ALTER priority SET DEFAULT 10");

        $this->getConnection()->createQueryBuilder()
            ->update('file_type')
            ->set('priority', ':priority')
            ->where('priority IS NULL')
            ->setParameter('priority', 0, ParameterType::INTEGER)
            ->executeQuery();

        $this->exec("ALTER TABLE file_type ALTER priority SET NOT NULL");

        self::createDefaultStorage($this->getConnection());

        $this->migrateAssetCategories();
        $this->migrateAssetTypes();
        $this->migrateAssets();

        foreach (['globalSearchEntityList', 'tabList', 'twoLevelTabList', 'quickCreateList'] as $confName) {
            $conf = $this->getConfig()->get($confName, []);
            foreach (['File', 'Folder'] as $v) {
                if (!in_array($v, $conf)) {
                    $conf[] = $v;
                }
            }

            foreach (['Asset', 'AssetCategory', 'Library'] as $v) {
                $key = array_search($v, $conf);
                if ($key !== false) {
                    unset($conf[$key]);
                }
            }

            $this->getConfig()->set($confName, array_values($conf));
        }

        $this->getConfig()->remove('whitelistedExtensions');
        $this->getConfig()->save();

//        $this->exec("DROP TABLE asset_asset");
//        $this->exec("DROP TABLE asset_category");
//        $this->exec("DROP TABLE asset_category_asset");
//        $this->exec("DROP TABLE asset_category_hierarchy");
//        $this->exec("DROP TABLE asset_hierarchy");
//        $this->exec("DROP TABLE asset_metadata");
//        $this->exec("DROP TABLE asset_type");
//        $this->exec("DROP TABLE library");
//        $this->exec("DROP TABLE library_asset_category");
//        $this->exec("DROP TABLE asset");
//        $this->exec("DROP TABLE attachment");

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
                ->setParameter('modifiedAt', $v['modified_at'] ?? null)
                ->setParameter('createdById', $v['created_by_id'])
                ->setParameter('modifiedById', $v['modified_by_id'] ?? null);
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

        $path = 'custom/Espo/Custom/Resources/metadata/entityDefs';
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                if ($file === 'Asset.json') {
                    rename("$path/$file", "$path/File.json");
                    continue;
                }

                $toUpdate = false;

                $metadata = json_decode(file_get_contents("$path/$file"), true);

                if (!empty($metadata['fields'])) {
                    foreach ($metadata['fields'] as $field => $fieldDefs) {
                        if (!empty($fieldDefs['type'])) {
                            if (in_array($fieldDefs['type'], ['asset', 'attachment', 'image'])) {
                                $metadata['fields'][$field]['type'] = 'file';
                                if (!empty($fieldDefs['assetType'])) {
                                    try {
                                        $fileType = $this->getConnection()->createQueryBuilder()
                                            ->select('*')
                                            ->from('file_type')
                                            ->where('deleted=:false')
                                            ->andWhere('name=:name')
                                            ->setParameter('false', false, ParameterType::BOOLEAN)
                                            ->setParameter('name', $fieldDefs['assetType'])
                                            ->fetchAssociative();
                                        if (!empty($fileType)) {
                                            $metadata['fields'][$field]['fileTypeId'] = $fileType['id'];
                                            unset($metadata['fields'][$field]['assetType']);
                                        }
                                    } catch (\Throwable $e) {
                                    }
                                }
                                $toUpdate = true;
                            }

                            if ($fieldDefs['type'] === 'attachmentMultiple') {
                                unset($metadata['fields'][$field]);
                                if (isset($metadata['links'][$field])) {
                                    unset($metadata['links'][$field]);
                                }
                                $toUpdate = true;
                            }
                        }
                    }
                }

                if (!empty($metadata['links'])) {
                    foreach ($metadata['links'] as $link => $linkDefs) {
                        if (!empty($linkDefs['entity']) && !empty($linkDefs['type'])) {
                            if ($linkDefs['type'] === 'hasMany' && $linkDefs['entity'] === 'Asset') {
                                $metadata['links'][$link]['entity'] = 'File';

                                $table = Util::toUnderScore($metadata['links'][$link]['relationName']);
                                $column = Util::toUnderScore(lcfirst(str_replace('.json', '', $file))) . '_id';

                                if ($this->isPgSQL()) {
                                    $this->exec("DROP INDEX idx_{$table}_asset_id");
                                    $this->exec("DROP INDEX IDX_" . strtoupper($table) . "_UNIQUE_RELATION");
                                    $this->exec("ALTER TABLE {$table} ADD file_id VARCHAR(24) DEFAULT NULL");
                                    $this->exec("CREATE INDEX IDX_" . strtoupper($table) . "_FILE_ID ON {$table} (file_id, deleted)");
                                    $this->exec("CREATE UNIQUE INDEX IDX_" . strtoupper($table) . "_UNIQUE_RELATION ON {$table} (deleted, file_id, {$column})");
                                } else {
                                    $this->exec("DROP INDEX IDX_" . strtoupper($table) . "_ASSET_ID ON {$table}");
                                    $this->exec("DROP INDEX IDX_" . strtoupper($table) . "_UNIQUE_RELATION ON {$table}");
                                    $this->exec("ALTER TABLE {$table} ADD file_id VARCHAR(24) DEFAULT NULL");
                                    $this->exec("CREATE INDEX IDX_" . strtoupper($table) . "_FILE_ID ON {$table} (file_id, deleted)");
                                    $this->exec("CREATE UNIQUE INDEX IDX_" . strtoupper($table) . "_UNIQUE_RELATION ON {$table} (deleted, file_id, {$column})");
                                }

                                $res = $this->getConnection()->createQueryBuilder()
                                    ->select('t1.*, a.file_id')
                                    ->from($this->getConnection()->quoteIdentifier($table), 't1')
                                    ->join('t1', 'asset', 'a', 't1.asset_id=a.id')
                                    ->where('t1.deleted=:false')
                                    ->andWhere('a.deleted=:false')
                                    ->setParameter('false', false, ParameterType::BOOLEAN)
                                    ->fetchAllAssociative();

                                foreach ($res as $v) {
                                    if (!empty($v['file_id'])) {
                                        $this->getConnection()->createQueryBuilder()
                                            ->update($this->getConnection()->quoteIdentifier($table))
                                            ->set('file_id', ':fileId')
                                            ->where('id=:id')
                                            ->setParameter('id', $v['id'])
                                            ->setParameter('fileId', $v['file_id'])
                                            ->executeQuery();
                                    }
                                }

                                $toUpdate = true;
                            }
                            if ($linkDefs['type'] === 'belongsTo' && $linkDefs['entity'] === 'Attachment') {
                                unset($metadata['links'][$link]);
                                $toUpdate = true;
                            }
                        }
                    }
                }

                if ($toUpdate) {
                    file_put_contents("$path/$file", json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
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
                'id'                  => 'a_favicon',
                'name'                => 'Icon',
                'assignAutomatically' => true,
                'extensions'          => ['ico', 'png', 'svg']
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
