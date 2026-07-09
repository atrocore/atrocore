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
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\Core\Utils\IdGenerator;

class V2Dot3Dot10 extends Base
{
    private ?Metadata $metadata = null;

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-18 17:00:00');
    }

    public function up(): void
    {
        $this->migrateMatchings();
        $this->migrateDerivativeMiddle();
        $this->migrateMasterDataEntity();
        $this->renameMasterDataEntityToConsolidation();
        $this->migrateConsolidation();
        $this->migrateDataPipelines();
    }

    public function migrateConsolidation(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE SEQUENCE consolidation_number_seq INCREMENT BY 1 MINVALUE 1 START 1");
            $this->exec("DROP INDEX uniq_3fe49d9b5e237e06eb3b4e33");
            $this->exec("ALTER TABLE consolidation ADD number INT DEFAULT nextval('consolidation_number_seq') NOT NULL");
            $this->exec("ALTER TABLE consolidation ADD entity_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE consolidation RENAME COLUMN merging_script TO consolidation_script");
        } else {
            $this->exec("DROP INDEX UNIQ_3FE49D9B5E237E06EB3B4E33 ON consolidation");
            $this->exec("LTER TABLE consolidation ADD number INT NOT NULL, ADD entity_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_3FE49D9B96901F54 ON consolidation (number)");
            $this->exec("ALTER TABLE consolidation CHANGE number number INT AUTO_INCREMENT NOT NULL");
            $this->exec("ALTER TABLE consolidation CHANGE merging_script consolidation_script LONGTEXT DEFAULT NULL");
        }

        $this->exec("CREATE UNIQUE INDEX IDX_CONSOLIDATION_UNIQUE_ENTITY ON consolidation (deleted, entity_id)");

        $this->getDbal()->createQueryBuilder()
            ->update('consolidation')
            ->set('entity_id', 'name')
            ->executeQuery();
    }

    public function migrateDataPipelines():void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE data_pipeline (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', merging_script TEXT DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, source_entity_id VARCHAR(36) DEFAULT NULL, target_entity_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX UNIQ_B7221005D1B862B8EB3B4E33 ON data_pipeline (hash, deleted)");
            $this->exec("CREATE INDEX IDX_DATA_PIPELINE_CREATED_BY_ID ON data_pipeline (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_DATA_PIPELINE_MODIFIED_BY_ID ON data_pipeline (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_DATA_PIPELINE_SOURCE_ENTITY_ID ON data_pipeline (source_entity_id, deleted)");
            $this->exec("CREATE INDEX IDX_DATA_PIPELINE_TARGET_ENTITY_ID ON data_pipeline (target_entity_id, deleted)");
            $this->exec("CREATE INDEX IDX_DATA_PIPELINE_CREATED_AT ON data_pipeline (created_at, deleted)");
            $this->exec("CREATE SEQUENCE data_pipeline_number_seq INCREMENT BY 1 MINVALUE 1 START 1");
            $this->exec("ALTER TABLE data_pipeline ADD number INT DEFAULT nextval('data_pipeline_number_seq') NOT NULL");
        } else {
            $this->exec("CREATE TABLE data_pipeline (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', merging_script LONGTEXT DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL COLLATE `utf8_bin`, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, source_entity_id VARCHAR(36) DEFAULT NULL, target_entity_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_B7221005D1B862B8EB3B4E33 (hash, deleted), INDEX IDX_DATA_PIPELINE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_DATA_PIPELINE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_DATA_PIPELINE_SOURCE_ENTITY_ID (source_entity_id, deleted), INDEX IDX_DATA_PIPELINE_TARGET_ENTITY_ID (target_entity_id, deleted), INDEX IDX_DATA_PIPELINE_CREATED_AT (created_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("ALTER TABLE data_pipeline ADD number INT NOT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_B722100596901F54 ON data_pipeline (number)");
            $this->exec("ALTER TABLE data_pipeline CHANGE number number INT AUTO_INCREMENT NOT NULL");
        }
    }

    public function renameMasterDataEntityToConsolidation(): void
    {
        $this->exec("ALTER TABLE master_data_entity RENAME TO consolidation");
        $this->exec("ALTER TABLE user_followed_master_data_entity RENAME TO user_followed_consolidation");

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE user_followed_consolidation RENAME COLUMN master_data_entity_id TO consolidation_id");
            $this->exec("ALTER TABLE consolidation RENAME CONSTRAINT master_data_entity_pkey TO consolidation_pkey");
            $this->exec("ALTER TABLE user_followed_consolidation RENAME CONSTRAINT user_followed_master_data_entity_pkey TO user_followed_consolidation_pkey");

            foreach (['created_by_id', 'modified_by_id', 'owner_user_id', 'assigned_user_id', 'created_at', 'modified_at'] as $name) {
                $this->exec("ALTER INDEX idx_master_data_entity_{$name} RENAME TO IDX_CONSOLIDATION_" . strtoupper($name));
            }
            $this->exec("ALTER INDEX uniq_64dec5f45e237e06eb3b4e33 RENAME TO UNIQ_3FE49D9B5E237E06EB3B4E33");

            foreach (['unique_relation', 'created_by_id', 'modified_by_id', 'user_id', 'created_at', 'modified_at'] as $name) {
                $this->exec("ALTER INDEX idx_user_followed_master_data_entity_{$name} RENAME TO IDX_USER_FOLLOWED_CONSOLIDATION_" . strtoupper($name));
            }
            $this->exec("ALTER INDEX idx_user_followed_master_data_entity_master_data_entity_id RENAME TO IDX_USER_FOLLOWED_CONSOLIDATION_CONSOLIDATION_ID");
        } else {
            $this->exec("ALTER TABLE user_followed_consolidation CHANGE master_data_entity_id consolidation_id VARCHAR(36) DEFAULT NULL");

            foreach (['created_by_id', 'modified_by_id', 'owner_user_id', 'assigned_user_id', 'created_at', 'modified_at'] as $name) {
                $this->exec("ALTER TABLE consolidation RENAME INDEX idx_master_data_entity_{$name} TO IDX_CONSOLIDATION_" . strtoupper($name));
            }
            $this->exec("ALTER TABLE consolidation RENAME INDEX UNIQ_64DEC5F45E237E06EB3B4E33 TO UNIQ_3FE49D9B5E237E06EB3B4E33");

            foreach (['unique_relation', 'created_by_id', 'modified_by_id', 'user_id', 'created_at', 'modified_at'] as $name) {
                $this->exec("ALTER TABLE user_followed_consolidation RENAME INDEX idx_user_followed_master_data_entity_{$name} TO IDX_USER_FOLLOWED_CONSOLIDATION_" . strtoupper($name));
            }
            $this->exec("ALTER TABLE user_followed_consolidation RENAME INDEX idx_user_followed_master_data_entity_master_data_entity_id TO IDX_USER_FOLLOWED_CONSOLIDATION_CONSOLIDATION_ID");
        }

        // update entity name references in system tables
        $columns = [
            'note'                  => ['parent_type'],
            'action_history_record' => ['target_type'],
            'bookmark'              => ['entity_type'],
            'saved_search'          => ['entity_type'],
            'notification'          => ['related_type', 'related_parent_type'],
            'layout'                => ['entity', 'related_entity'],
        ];
        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                try {
                    $this->getDbal()->createQueryBuilder()
                        ->update($this->getDbal()->quoteIdentifier($table))
                        ->set($column, ':new')
                        ->where("$column = :old")
                        ->setParameter('new', 'Consolidation')
                        ->setParameter('old', 'MasterDataEntity')
                        ->executeQuery();
                } catch (\Throwable $e) {
                }
            }
        }

        // update config scope lists
        foreach (['tabList', 'quickCreateList'] as $key) {
            $list = $this->getConfig()->get($key);
            if (is_array($list) && in_array('MasterDataEntity', $list)) {
                $this->getConfig()->set($key, array_map(fn($item) => $item === 'MasterDataEntity' ? 'Consolidation' : $item, $list));
                $this->getConfig()->save();
            }
        }
    }

    public function migrateMasterDataEntity(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE master_data_entity ADD name VARCHAR(255) DEFAULT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_64DEC5F45E237E06EB3B4E33 ON master_data_entity (name, deleted)");
        } else {
            $this->exec("ALTER TABLE master_data_entity ADD name VARCHAR(255) DEFAULT NULL COLLATE `utf8_bin`");
            $this->exec("CREATE UNIQUE INDEX UNIQ_64DEC5F45E237E06EB3B4E33 ON master_data_entity (name, deleted)");
        }

        $derivatives = [];
        foreach ($this->getMetadata()->get('scopes') ?? [] as $entityName => $defs) {
            if (!empty($defs['primaryEntityId'])) {
                $derivatives[] = $entityName;
            }
        }

        if (!empty($derivatives)) {
            $this->getDbal()->createQueryBuilder()
                ->delete('master_data_entity')
                ->where('id IN (:ids)')
                ->setParameter('ids', $derivatives, $this->getDbal()::PARAM_STR_ARRAY)
                ->executeQuery();
        }

        $res = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from('master_data_entity')
            ->where('name IS NULL')
            ->fetchAllAssociative();

        foreach ($res as $row) {
            $this->getDbal()->createQueryBuilder()
                ->update('master_data_entity')
                ->set('name', 'id')
                ->set('id', ':uuid')
                ->set('merging_script', ':mergingScript')
                ->where('id = :id')
                ->setParameter('id', $row['id'])
                ->setParameter('uuid', IdGenerator::uuid())
                ->setParameter('mergingScript', str_replace('stagingRecord', 'contributorRecord', $row['merging_script']))
                ->executeQuery();
        }

        $path = 'data/metadata/scopes';

        if (is_dir($path)) {
            foreach (scandir($path) as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                $scopeData = @json_decode(file_get_contents("$path/$file"), true);

                if (is_array($scopeData) && ($scopeData['role'] ?? null) === 'staging') {
                    $scopeData['role'] = 'contributor';
                    file_put_contents("$path/$file", json_encode($scopeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
        }
    }

    public function migrateMatchings(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE matching ADD name VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE matching ADD code VARCHAR(255) DEFAULT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_DC10F28977153098EB3B4E33 ON matching (code, deleted)");
        } else {
            $this->exec("ALTER TABLE matching ADD name VARCHAR(255) DEFAULT NULL, ADD code VARCHAR(255) DEFAULT NULL COLLATE `utf8_bin`");
            $this->exec("CREATE UNIQUE INDEX UNIQ_DC10F28977153098EB3B4E33 ON matching (code, deleted)");
        }

        $this->getDbal()->createQueryBuilder()
            ->delete('matching')
            ->where('deleted = :true')
            ->setParameter('true', true, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->executeQuery();

        try {
            $res = $this->getDbal()->createQueryBuilder()
                ->select('*')
                ->from('matching')
                ->where('deleted = :false')
                ->andWhere('code IS NULL')
                ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable) {
            $res = [];
        }

        foreach ($res as $item) {
            $tableName = Util::toUnderScore(lcfirst($item['entity']));

            if ($this->isPgSQL()) {
                $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " RENAME COLUMN matching_{$tableName}_s2m to {$tableName}_c2m");
                $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " RENAME COLUMN matching_{$tableName}_d2d to {$tableName}_d2d");
            } else {
                $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " CHANGE matching_{$tableName}_s2m {$tableName}_c2m DATETIME DEFAULT NULL");
                $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " CHANGE matching_{$tableName}_d2d {$tableName}_d2d DATETIME DEFAULT NULL");
            }

            $uuid = IdGenerator::uuid();
            $code = str_replace('-S2M', '-C2M', $item['id']);

            $this->getDbal()->createQueryBuilder()
                ->update('matching')
                ->set('name', ':name')
                ->set('code', ':code')
                ->set('id', ':uuid')
                ->where('id = :id')
                ->setParameters([
                    'name' => $code,
                    'code' => $code,
                    'id'   => $item['id'],
                    'uuid' => $uuid,
                ])
                ->executeQuery();

            $this->getDbal()->createQueryBuilder()
                ->update('matching_rule')
                ->set('matching_id', ':uuid')
                ->where('matching_id = :id')
                ->setParameters([
                    'id'   => $item['id'],
                    'uuid' => $uuid,
                ])
                ->executeQuery();

            $this->getDbal()->createQueryBuilder()
                ->update('matched_record')
                ->set('matching_id', ':uuid')
                ->where('matching_id = :id')
                ->setParameters([
                    'id'   => $item['id'],
                    'uuid' => $uuid,
                ])
                ->executeQuery();
        }

        // the matchings activation state is kept in the matching table and cached via metadata now
        $this->getConfig()->remove('matchings');
        $this->getConfig()->save();
    }

    public function migrateDerivativeMiddle(): void
    {
        $metadata = $this->getMetadata();

        foreach ($metadata->get('scopes') ?? [] as $scope => $scopeDefs) {
            if (empty($scopeDefs['primaryEntityId'])) {
                continue;
            }

            $primaryEntity = $scopeDefs['primaryEntityId'];

            $entityDefs = $metadata->get("entityDefs.$primaryEntity");

            if (empty($entityDefs['fields'])) {
                continue;
            }

            foreach ($entityDefs['fields'] as $fieldName => $fieldDefs) {
                if (empty($fieldDefs['type'])) {
                    continue;
                }

                if ($fieldDefs['type'] === 'linkMultiple') {
                    $linkDefs = $entityDefs['links'][$fieldName] ?? null;

                    if (!empty($linkDefs['relationName'])) {
                        if ($linkDefs['relationName'] !== "{$linkDefs['entity']}Hierarchy") {
                            $newName = $scope . $linkDefs['entity'];

                            $i = 2;
                            while (!empty($data['entityDefs'][$newName])) {
                                $newName = $newName . $i;
                                $i++;
                            }

                            $oldName = Util::toUnderScore('derivativeMiddle_' . md5("{$linkDefs['relationName']}_$scope"));
                            $newName = Util::toUnderScore($newName);

                            $this->exec("ALTER TABLE $oldName RENAME TO $newName");
                        }
                    }
                }
            }
        }
    }

    private function getMetadata(): Metadata
    {
        if ($this->metadata === null) {
            $this->metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');
        }

        return $this->metadata;
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}