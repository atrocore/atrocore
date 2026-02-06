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
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-12-16 17:00:00');
    }

    public function up(): void
    {
        $this->step1();
        $this->step2();
        $this->step3();
        $this->step4();
        $this->step5();
        $this->step6();
        $this->step7();
        $this->step8();
        $this->step9();
        $this->step10();
        $this->step11();
        $this->step12();
        $this->step13();
    }

    protected function step1(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE action ADD icon_class VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE action ADD hide_text_label BOOLEAN DEFAULT 'false' NOT NULL");
        } else {
            $this->exec("ALTER TABLE action ADD icon_class VARCHAR(255) DEFAULT NULL, ADD hide_text_label TINYINT(1) DEFAULT '0' NOT NULL");
        }
    }

    protected function step2(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE selection ADD type VARCHAR(255) DEFAULT NULL");
        } else {
            $this->exec("ALTER TABLE selection ADD type VARCHAR(255) DEFAULT NULL");
        }
    }

    protected function step3(): void
    {
        $this->exec("DROP TABLE matched_record");

        if ($this->isPgSQL()) {
            $this->exec(
                "CREATE TABLE matched_record (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', type VARCHAR(255) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, source_entity_id VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, master_entity_id VARCHAR(255) DEFAULT NULL, score INT DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX UNIQ_A88D469ED1B862B8EB3B4E33 ON matched_record (hash, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_CREATED_BY_ID ON matched_record (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MODIFIED_BY_ID ON matched_record (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_STAGING_ENTITY ON matched_record (type, source_entity, source_entity_id)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MASTER_ENTITY ON matched_record (type, master_entity, master_entity_id)");
        } else {
            $this->exec(
                "CREATE TABLE matched_record (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', type VARCHAR(255) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, source_entity_id VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, master_entity_id VARCHAR(255) DEFAULT NULL, score INT DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_A88D469ED1B862B8EB3B4E33 (hash, deleted), INDEX IDX_MATCHED_RECORD_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MATCHED_RECORD_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_MATCHED_RECORD_STAGING_ENTITY (type, source_entity, source_entity_id), INDEX IDX_MATCHED_RECORD_MASTER_ENTITY (type, master_entity, master_entity_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
        }

        if (file_exists('data/reference-data/Matching.json')) {
            @unlink('data/reference-data/Matching.json');
        }

        if (file_exists('data/reference-data/MatchingRule.json')) {
            @unlink('data/reference-data/MatchingRule.json');
        }
    }

    protected function step4(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('layout')
            ->set('view_type', ':newType')
            ->where('view_type = :oldType')
            ->setParameter('newType', 'navigation')
            ->setParameter('oldType', 'leftSidebar')
            ->executeStatement();

        $this->getConnection()->createQueryBuilder()
            ->update('layout')
            ->set('view_type', ':newType')
            ->where('view_type = :oldType')
            ->setParameter('newType', 'summary')
            ->setParameter('oldType', 'rightSideView')
            ->executeStatement();

        $res = $this->getConnection()->createQueryBuilder()
            ->select('id', 'entity', 'view_type', 'layout_profile_id')
            ->from('layout')
            ->where('view_type = :viewType and deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('viewType', 'summary')
            ->fetchAllAssociative();


        $path = 'data/reference-data/QualityCheck.json';
        $content = [];
        if (file_exists($path)) {
            $content = @json_decode(@file_get_contents($path), true) ?? [];
        }


        foreach ($res as $row) {
            try {
                $id = IdGenerator::uuid();
                $this->getConnection()->createQueryBuilder()
                    ->insert('layout')
                    ->values([
                        'id'                => ':id',
                        'entity'            => ':entity',
                        'view_type'         => ':viewType',
                        'layout_profile_id' => ':layoutProfileId',
                    ])
                    ->setParameter('id', $id)
                    ->setParameter('entity', $row['entity'])
                    ->setParameter('viewType', 'insights')
                    ->setParameter('layoutProfileId', $row['layout_profile_id'])
                    ->executeStatement();

                $this->getConnection()->createQueryBuilder()
                    ->insert('layout_list_item')
                    ->values([
                        'id'         => ':id',
                        'layout_id'  => ':layoutId',
                        'name'       => ':name',
                        'sort_order' => ':sortOrder'
                    ])
                    ->setParameter('id', IdGenerator::uuid())
                    ->setParameter('layoutId', $id)
                    ->setParameter('name', 'summary')
                    ->setParameter('sortOrder', 10)
                    ->executeStatement();

                // add dataQuality panel if enabled
                foreach ($content as $item) {
                    if ($item['entityId'] === $row['entity']) {
                        $this->getConnection()->createQueryBuilder()
                            ->insert('layout_list_item')
                            ->values([
                                'id'         => ':id',
                                'layout_id'  => ':layoutId',
                                'name'       => ':name',
                                'sort_order' => ':sortOrder'
                            ])
                            ->setParameter('id', IdGenerator::uuid())
                            ->setParameter('layoutId', $id)
                            ->setParameter('name', 'dataQuality')
                            ->setParameter('sortOrder', 20)
                            ->executeStatement();
                        break;
                    }
                }
            } catch (\Throwable $e) {
            }
        }
    }

    protected function step5(): void
    {
        if ($this->isPgSQL()) {
            $this->exec(
                "CREATE TABLE matching (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', code VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, minimum_score INT DEFAULT 100, entity VARCHAR(255) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX UNIQ_DC10F28977153098EB3B4E33 ON matching (code, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_CREATED_BY_ID ON matching (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_MODIFIED_BY_ID ON matching (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_OWNER_USER_ID ON matching (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_ASSIGNED_USER_ID ON matching (assigned_user_id, deleted)");

            $this->exec(
                "CREATE TABLE matching_rule (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, weight INT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, master_field VARCHAR(255) DEFAULT NULL, source_field VARCHAR(255) DEFAULT NULL, operator VARCHAR(255) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, matching_rule_set_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_MATCHING_ID ON matching_rule (matching_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_MATCHING_RULE_SET_ID ON matching_rule (matching_rule_set_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_CREATED_BY_ID ON matching_rule (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_MODIFIED_BY_ID ON matching_rule (modified_by_id, deleted)");

            $this->exec(
                "CREATE TABLE user_followed_matching (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_MATCHING_UNIQUE_RELATION ON user_followed_matching (deleted, matching_id, user_id)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_CREATED_BY_ID ON user_followed_matching (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_MODIFIED_BY_ID ON user_followed_matching (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_MATCHING_ID ON user_followed_matching (matching_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_USER_ID ON user_followed_matching (user_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_CREATED_AT ON user_followed_matching (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_MODIFIED_AT ON user_followed_matching (modified_at, deleted)");

            $this->exec(
                "CREATE TABLE user_followed_matching_rule (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, matching_rule_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_UNIQUE_RELATION ON user_followed_matching_rule (deleted, matching_rule_id, user_id)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_CREATED_BY_ID ON user_followed_matching_rule (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MODIFIED_BY_ID ON user_followed_matching_rule (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MATCHING_RULE_ID ON user_followed_matching_rule (matching_rule_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_USER_ID ON user_followed_matching_rule (user_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_CREATED_AT ON user_followed_matching_rule (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MODIFIED_AT ON user_followed_matching_rule (modified_at, deleted)");
        } else {
            $this->exec(
                "CREATE TABLE matching (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', code VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, minimum_score INT DEFAULT 100, entity VARCHAR(255) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_DC10F28977153098EB3B4E33 (code, deleted), INDEX IDX_MATCHING_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MATCHING_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_MATCHING_OWNER_USER_ID (owner_user_id, deleted), INDEX IDX_MATCHING_ASSIGNED_USER_ID (assigned_user_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE matching_rule (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, weight INT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, master_field VARCHAR(255) DEFAULT NULL, source_field VARCHAR(255) DEFAULT NULL, operator VARCHAR(255) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, matching_rule_set_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, INDEX IDX_MATCHING_RULE_MATCHING_ID (matching_id, deleted), INDEX IDX_MATCHING_RULE_MATCHING_RULE_SET_ID (matching_rule_set_id, deleted), INDEX IDX_MATCHING_RULE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MATCHING_RULE_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE user_followed_matching (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_MATCHING_UNIQUE_RELATION (deleted, matching_id, user_id), INDEX IDX_USER_FOLLOWED_MATCHING_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_MATCHING_ID (matching_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE user_followed_matching_rule (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, matching_rule_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_UNIQUE_RELATION (deleted, matching_rule_id, user_id), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MATCHING_RULE_ID (matching_rule_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
        }

        if (file_exists("data/reference-data/Matching.json")) {
            $matchings = @json_decode(file_get_contents("data/reference-data/Matching.json"), true);
            if (is_array($matchings)) {
                foreach ($matchings as $matching) {
                    $qb = $this->getConnection()->createQueryBuilder();
                    $qb->insert('matching');
                    foreach ($matching as $field => $value) {
                        $column = Util::toUnderScore($field);
                        if (!in_array(
                            $column,
                            ['id', 'name', 'code', 'created_at', 'modified_at', 'type', 'description', 'minimum_score', 'entity', 'source_entity', 'master_entity', 'is_active',
                                'created_by_id', 'modified_by_id']
                        )) {
                            continue;
                        }
                        $qb->setValue($column, ':' . $field)->setParameter($field, $value, Mapper::getParameterType($value));
                    }
                    try {
                        $qb->executeQuery();
                    } catch (\Throwable $e) {
                    }

                    $filePath = "data/metadata/scopes/{$matching['sourceEntity']}.json";
                    if (file_exists($filePath)) {
                        $metadata = json_decode(file_get_contents("data/metadata/scopes/{$matching['sourceEntity']}.json"), true);
                        if (!is_array($metadata)) {
                            $metadata = [];
                        }
                    } else {
                        $metadata = [];
                    }

                    if (!empty($matching['type'])) {
                        if ($matching['type'] === 'duplicate') {
                            $metadata['hasDuplicates'] = true;
                        } else {
                            if (!empty($matching['masterEntity'])) {
                                $metadata['masterEntity'] = $matching['masterEntity'];
                            }
                        }
                    }

                    if (!empty($metadata)) {
                        file_put_contents($filePath, json_encode($metadata));
                    }
                }
            }

            unlink("data/reference-data/Matching.json");
        }

        if (file_exists("data/reference-data/MatchingRule.json")) {
            $rules = @json_decode(file_get_contents("data/reference-data/MatchingRule.json"), true);
            if (is_array($rules)) {
                foreach ($rules as $rule) {
                    $qb = $this->getConnection()->createQueryBuilder();
                    $qb->insert('matching_rule');
                    foreach ($rule as $field => $value) {
                        $column = Util::toUnderScore($field);
                        if (!in_array(
                            $column,
                            ['id', 'name', 'code', 'created_at', 'modified_at', 'weight', 'type', 'master_field', 'source_field', 'operator', 'matching_id', 'matching_rule_set_id',
                                'created_by_id', 'modified_by_id']
                        )) {
                            continue;
                        }

                        $qb->setValue($column, ':' . $field)->setParameter($field, $value, Mapper::getParameterType($value));
                    }
                    try {
                        $qb->executeQuery();
                    } catch (\Throwable $e) {
                    }
                }
            }
            unlink("data/reference-data/MatchingRule.json");
        }
    }

    protected function step6(): void
    {
        $this->exec("ALTER TABLE matched_record ADD matching_id VARCHAR(36) DEFAULT NULL");

        if ($this->isPgSQL()) {
            $this->exec(
                "CREATE TABLE master_data_entity (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', mapping_script TEXT DEFAULT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_CREATED_BY_ID ON master_data_entity (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_MODIFIED_BY_ID ON master_data_entity (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_OWNER_USER_ID ON master_data_entity (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_ASSIGNED_USER_ID ON master_data_entity (assigned_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_CREATED_AT ON master_data_entity (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_MODIFIED_AT ON master_data_entity (modified_at, deleted)");

            $this->exec(
                "CREATE TABLE user_followed_master_data_entity (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, master_data_entity_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_UNIQUE_RELATION ON user_followed_master_data_entity (deleted, master_data_entity_id, user_id)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_CREATED_BY_ID ON user_followed_master_data_entity (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MODIFIED_BY_ID ON user_followed_master_data_entity (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MASTER_DATA_ENTITY_ID ON user_followed_master_data_entity (master_data_entity_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_USER_ID ON user_followed_master_data_entity (user_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_CREATED_AT ON user_followed_master_data_entity (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MODIFIED_AT ON user_followed_master_data_entity (modified_at, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MATCHING_ID ON matched_record (matching_id, deleted)");
        } else {
            $this->exec(
                "CREATE TABLE master_data_entity (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', mapping_script LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, INDEX IDX_MASTER_DATA_ENTITY_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_OWNER_USER_ID (owner_user_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_ASSIGNED_USER_ID (assigned_user_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_CREATED_AT (created_at, deleted), INDEX IDX_MASTER_DATA_ENTITY_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE user_followed_master_data_entity (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, master_data_entity_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_UNIQUE_RELATION (deleted, master_data_entity_id, user_id), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MASTER_DATA_ENTITY_ID (master_data_entity_id, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
        }

        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('matching')
                ->where('deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $item) {
            try {
                $this->getConnection()->createQueryBuilder()
                    ->insert('master_data_entity')
                    ->setValue('id', ':id')
                    ->setValue('owner_user_id', ':system')
                    ->setValue('assigned_user_id', ':system')
                    ->setValue('created_at', ':date')
                    ->setValue('modified_at', ':date')
                    ->setValue('created_by_id', ':system')
                    ->setValue('modified_by_id', ':system')
                    ->setParameter('id', $item['id'])
                    ->setParameter('system', 'system')
                    ->setParameter('date', date('Y-m-d H:i:s'))
                    ->executeQuery();
            } catch (\Throwable $e) {

            }
        }
    }

    protected function step7(): void
    {
        $this->exec("ALTER TABLE matching ADD matched_records_max INT DEFAULT NULL");
    }

    protected function step8(): void
    {
        $data = [
            'id'             => 'SendReports',
            'name'           => 'Send anonymous error reports to AtroCore',
            'type'           => 'SendReports',
            'is_active'      => $this->getConfig()->get('reportingEnabled', false),
            'scheduling'     => '*/15 * * * *',
            'created_at'     => date('Y-m-d H:i:s'),
            'modified_at'    => date('Y-m-d H:i:s'),
            'created_by_id'  => 'system',
            'modified_by_id' => 'system',
        ];

        $qb = $this->getConnection()->createQueryBuilder();
        $qb->insert('scheduled_job');

        foreach ($data as $columnName => $value) {
            $qb->setValue($columnName, ":$columnName");
            $qb->setParameter($columnName, $value, Mapper::getParameterType($value));
        }

        try {
            $qb->executeQuery();
        } catch (\Throwable $e) {
        }
    }

    protected function step9(): void
    {
        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        $entityDefsPath = 'data/metadata/entityDefs';

        if (!file_exists($entityDefsPath)) {
            return;
        }

        foreach (array_diff(scandir($entityDefsPath), ['.', '..']) as $file) {
            $entity = explode('.', $file, 2)[0];


            $data = json_decode(@file_get_contents($entityDefsPath . '/' . $file), true);
            $changed = false;
            foreach ($data['fields'] ?? [] as $field => $fieldDefs) {
                if ($metadata->get(['entityDefs', $entity, 'fields', $field, 'type']) === 'linkMultiple') {
                    if (!empty($fieldDefs['required'])) {
                        unset($data['fields'][$field]['required']);
                        $changed = true;
                    }
                    if (!empty($fieldDefs['conditionalProperties']['required'])) {
                        unset($data['fields'][$field]['conditionalProperties']['required']);
                        if (empty($data['fields'][$field]['conditionalProperties'])) {
                            unset($data['fields'][$field]['conditionalProperties']);
                        }
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                file_put_contents($entityDefsPath . '/' . $file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        // add default unit value for fields with measure
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        try {
            foreach ($metadata->get(['entityDefs']) as $entityName => $entityDefs) {
                $tableName = Util::toUnderScore($entityName);
                if ($toSchema->hasTable($tableName)) {
                    $table = $toSchema->getTable($tableName);
                    foreach ($entityDefs['fields'] as $fieldName => $fieldDefs) {
                        $columnName = Util::toUnderScore($fieldName . 'UnitId');
                        if (!empty($fieldDefs['measureId']) && !empty($fieldDefs['defaultUnit'])
                            && in_array(
                                $fieldDefs['type'] ?? null, ['varchar', 'int', 'float', 'rangeInt', 'rangeFloat']
                            )
                            && $table->hasColumn($columnName)
                            && empty($table->getColumn($columnName)->getDefault())) {
                            $table->getColumn($columnName)->setDefault($fieldDefs['defaultUnit']);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }


        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    protected function step10(): void
    {
        $this->exec("DROP TABLE matching");
        $this->exec("DROP TABLE matching_rule");
        $this->exec("DROP TABLE matched_record");

        $this->exec("TRUNCATE master_data_entity");

        if ($this->isPgSQL()) {
            $this->exec(
                "CREATE TABLE matched_record (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', type VARCHAR(255) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, source_entity_id VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, master_entity_id VARCHAR(255) DEFAULT NULL, score INT DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX UNIQ_A88D469ED1B862B8EB3B4E33 ON matched_record (hash, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MATCHING_ID ON matched_record (matching_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_CREATED_BY_ID ON matched_record (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MODIFIED_BY_ID ON matched_record (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_SOURCE_ENTITY ON matched_record (type, source_entity, source_entity_id)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MASTER_ENTITY ON matched_record (type, master_entity, master_entity_id)");

            $this->exec(
                "CREATE TABLE matching (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', type VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, minimum_score INT DEFAULT 100, matched_records_max INT DEFAULT NULL, entity VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE INDEX IDX_MATCHING_CREATED_BY_ID ON matching (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_MODIFIED_BY_ID ON matching (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_OWNER_USER_ID ON matching (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_ASSIGNED_USER_ID ON matching (assigned_user_id, deleted)");

            $this->exec(
                "CREATE TABLE matching_rule (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', code VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, weight INT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, field VARCHAR(255) DEFAULT NULL, operator VARCHAR(255) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, matching_rule_set_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX UNIQ_BACFF97B77153098EB3B4E33 ON matching_rule (code, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_MATCHING_ID ON matching_rule (matching_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_MATCHING_RULE_SET_ID ON matching_rule (matching_rule_set_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_CREATED_BY_ID ON matching_rule (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_MODIFIED_BY_ID ON matching_rule (modified_by_id, deleted)");
        } else {
            $this->exec(
                "CREATE TABLE matched_record (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', type VARCHAR(255) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, source_entity_id VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, master_entity_id VARCHAR(255) DEFAULT NULL, score INT DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_A88D469ED1B862B8EB3B4E33 (hash, deleted), INDEX IDX_MATCHED_RECORD_MATCHING_ID (matching_id, deleted), INDEX IDX_MATCHED_RECORD_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MATCHED_RECORD_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_MATCHED_RECORD_SOURCE_ENTITY (type, source_entity, source_entity_id), INDEX IDX_MATCHED_RECORD_MASTER_ENTITY (type, master_entity, master_entity_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE matching (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', type VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, minimum_score INT DEFAULT 100, matched_records_max INT DEFAULT NULL, entity VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, INDEX IDX_MATCHING_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MATCHING_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_MATCHING_OWNER_USER_ID (owner_user_id, deleted), INDEX IDX_MATCHING_ASSIGNED_USER_ID (assigned_user_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
            $this->exec(
                "CREATE TABLE matching_rule (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', code VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, weight INT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, field VARCHAR(255) DEFAULT NULL, operator VARCHAR(255) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, matching_rule_set_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_BACFF97B77153098EB3B4E33 (code, deleted), INDEX IDX_MATCHING_RULE_MATCHING_ID (matching_id, deleted), INDEX IDX_MATCHING_RULE_MATCHING_RULE_SET_ID (matching_rule_set_id, deleted), INDEX IDX_MATCHING_RULE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MATCHING_RULE_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
        }
    }

    protected function step11(): void
    {
        $this->exec("ALTER TABLE action ADD search_entity VARCHAR(255) DEFAULT NULL");

        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from($this->getConnection()->quoteIdentifier('action'))
                ->where('type in (:types)')
                ->andWhere('deleted=:false')
                ->andWhere('target_entity IS NOT NULL')
                ->andWhere('search_entity IS NULL')
                ->setParameter('types', ['update', 'email', 'delete'], Connection::PARAM_STR_ARRAY)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $row) {
            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('action'))
                ->set('search_entity', ':searchEntity')
                ->where('id=:id')
                ->andWhere('source_entity IS NOT NULL')
                ->setParameter('id', $row['id'])
                ->setParameter('searchEntity', $row['target_entity'])
                ->executeQuery();
        }
    }

    protected function step12(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE action_execution (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', status VARCHAR(255) DEFAULT NULL, status_message TEXT DEFAULT NULL, payload TEXT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_count INT DEFAULT NULL, updated_count INT DEFAULT NULL, failed_count INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, action_id VARCHAR(36) DEFAULT NULL, incoming_webhook_id VARCHAR(36) DEFAULT NULL, scheduled_job_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, workflow_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_ACTION_ID ON action_execution (action_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_SCHEDULED_JOB_ID ON action_execution (scheduled_job_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_CREATED_BY_ID ON action_execution (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_MODIFIED_BY_ID ON action_execution (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_WORKFLOW_ID ON action_execution (workflow_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_NAME ON action_execution (name, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_STATUS ON action_execution (status, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_CREATED_AT ON action_execution (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_MODIFIED_AT ON action_execution (modified_at, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_STARTED_AT ON action_execution (modified_at, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_FINISHED_AT ON action_execution (modified_at, deleted)");
            $this->exec("COMMENT ON COLUMN action_execution.payload IS '(DC2Type:jsonObject)'");

            $this->exec("CREATE TABLE action_execution_log (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', type VARCHAR(10) DEFAULT NULL, entity_name VARCHAR(100) DEFAULT NULL, entity_id VARCHAR(36) DEFAULT NULL, message TEXT DEFAULT NULL, payload TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, action_execution_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_LOG_ACTION_EXECUTION_ID ON action_execution_log (action_execution_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_LOG_CREATED_BY_ID ON action_execution_log (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_LOG_MODIFIED_BY_ID ON action_execution_log (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_LOG_TYPE ON action_execution_log (type, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_LOG_ENTITY_NAME ON action_execution_log (entity_name, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_LOG_ENTITY_ID ON action_execution_log (entity_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_EXECUTION_LOG_CREATED_AT ON action_execution_log (created_at, deleted)");
            $this->exec("COMMENT ON COLUMN action_execution_log.payload IS '(DC2Type:jsonObject)'");
            $this->exec("DROP TABLE action_log");
        } else {
            $this->exec("CREATE TABLE action_execution (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', status VARCHAR(255) DEFAULT NULL, status_message LONGTEXT DEFAULT NULL, payload LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', type VARCHAR(255) DEFAULT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, created_count INT DEFAULT NULL, updated_count INT DEFAULT NULL, failed_count INT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, action_id VARCHAR(36) DEFAULT NULL, incoming_webhook_id VARCHAR(36) DEFAULT NULL, scheduled_job_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, workflow_id VARCHAR(36) DEFAULT NULL, INDEX IDX_ACTION_EXECUTION_ACTION_ID (action_id, deleted), INDEX IDX_ACTION_EXECUTION_SCHEDULED_JOB_ID (scheduled_job_id, deleted), INDEX IDX_ACTION_EXECUTION_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_ACTION_EXECUTION_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_ACTION_EXECUTION_WORKFLOW_ID (workflow_id, deleted), INDEX IDX_ACTION_EXECUTION_NAME (name, deleted), INDEX IDX_ACTION_EXECUTION_STATUS (status, deleted), INDEX IDX_ACTION_EXECUTION_CREATED_AT (created_at, deleted), INDEX IDX_ACTION_EXECUTION_MODIFIED_AT (modified_at, deleted), INDEX IDX_ACTION_EXECUTION_STARTED_AT (modified_at, deleted), INDEX IDX_ACTION_EXECUTION_FINISHED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE action_execution_log (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', type VARCHAR(10) DEFAULT NULL, entity_name VARCHAR(100) DEFAULT NULL, entity_id VARCHAR(36) DEFAULT NULL, message LONGTEXT DEFAULT NULL, payload LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, action_execution_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, INDEX IDX_ACTION_EXECUTION_LOG_ACTION_EXECUTION_ID (action_execution_id, deleted), INDEX IDX_ACTION_EXECUTION_LOG_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_ACTION_EXECUTION_LOG_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_ACTION_EXECUTION_LOG_TYPE (type, deleted), INDEX IDX_ACTION_EXECUTION_LOG_ENTITY_NAME (entity_name, deleted), INDEX IDX_ACTION_EXECUTION_LOG_ENTITY_ID (entity_id, deleted), INDEX IDX_ACTION_EXECUTION_LOG_CREATED_AT (created_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("DROP TABLE action_log");
        }
    }

    protected function step13(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('attribute')) {
            $table = $toSchema->getTable('attribute');

            if (!$table->hasColumn('modified_extended_disabled')) {
                $table->addColumn('modified_extended_disabled', 'boolean', ['default' => false, 'notnull' => true]);

                foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                    $this->exec($sql);
                }
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
