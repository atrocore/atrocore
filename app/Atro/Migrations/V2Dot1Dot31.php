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
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;

class V2Dot1Dot31 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-11-28 09:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE matching (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', code VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, minimum_score INT DEFAULT 100, entity VARCHAR(255) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX UNIQ_DC10F28977153098EB3B4E33 ON matching (code, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_CREATED_BY_ID ON matching (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_MODIFIED_BY_ID ON matching (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_OWNER_USER_ID ON matching (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_ASSIGNED_USER_ID ON matching (assigned_user_id, deleted)");

            $this->exec("CREATE TABLE matching_rule (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, weight INT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, master_field VARCHAR(255) DEFAULT NULL, source_field VARCHAR(255) DEFAULT NULL, operator VARCHAR(255) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, matching_rule_set_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_MATCHING_ID ON matching_rule (matching_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_MATCHING_RULE_SET_ID ON matching_rule (matching_rule_set_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_CREATED_BY_ID ON matching_rule (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHING_RULE_MODIFIED_BY_ID ON matching_rule (modified_by_id, deleted)");

            $this->exec("CREATE TABLE user_followed_matching (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_MATCHING_UNIQUE_RELATION ON user_followed_matching (deleted, matching_id, user_id)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_CREATED_BY_ID ON user_followed_matching (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_MODIFIED_BY_ID ON user_followed_matching (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_MATCHING_ID ON user_followed_matching (matching_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_USER_ID ON user_followed_matching (user_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_CREATED_AT ON user_followed_matching (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_MODIFIED_AT ON user_followed_matching (modified_at, deleted)");

            $this->exec("CREATE TABLE user_followed_matching_rule (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, matching_rule_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_UNIQUE_RELATION ON user_followed_matching_rule (deleted, matching_rule_id, user_id)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_CREATED_BY_ID ON user_followed_matching_rule (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MODIFIED_BY_ID ON user_followed_matching_rule (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MATCHING_RULE_ID ON user_followed_matching_rule (matching_rule_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_USER_ID ON user_followed_matching_rule (user_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_CREATED_AT ON user_followed_matching_rule (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MODIFIED_AT ON user_followed_matching_rule (modified_at, deleted)");
        } else {
            $this->exec("CREATE TABLE matching (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', code VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, minimum_score INT DEFAULT 100, entity VARCHAR(255) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_DC10F28977153098EB3B4E33 (code, deleted), INDEX IDX_MATCHING_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MATCHING_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_MATCHING_OWNER_USER_ID (owner_user_id, deleted), INDEX IDX_MATCHING_ASSIGNED_USER_ID (assigned_user_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE matching_rule (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, weight INT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, master_field VARCHAR(255) DEFAULT NULL, source_field VARCHAR(255) DEFAULT NULL, operator VARCHAR(255) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, matching_rule_set_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, INDEX IDX_MATCHING_RULE_MATCHING_ID (matching_id, deleted), INDEX IDX_MATCHING_RULE_MATCHING_RULE_SET_ID (matching_rule_set_id, deleted), INDEX IDX_MATCHING_RULE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MATCHING_RULE_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE user_followed_matching (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_MATCHING_UNIQUE_RELATION (deleted, matching_id, user_id), INDEX IDX_USER_FOLLOWED_MATCHING_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_MATCHING_ID (matching_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE user_followed_matching_rule (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, matching_rule_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_MATCHING_RULE_UNIQUE_RELATION (deleted, matching_rule_id, user_id), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MATCHING_RULE_ID (matching_rule_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_MATCHING_RULE_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }

        if (file_exists("data/reference-data/Matching.json")) {
            $matchings = @json_decode(file_get_contents("data/reference-data/Matching.json"), true);
            if (is_array($matchings)) {
                foreach ($matchings as $matching) {
                    $qb = $this->getConnection()->createQueryBuilder();
                    $qb->insert('matching');
                    foreach ($matching as $field => $value) {
                        $column = Util::toUnderScore($field);
                        if (!in_array($column, ['id', 'name', 'code', 'created_at', 'modified_at', 'type', 'description', 'minimum_score', 'entity', 'source_entity', 'master_entity', 'is_active', 'created_by_id', 'modified_by_id'])) {
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
                        if (!in_array($column, ['id', 'name', 'code', 'created_at', 'modified_at', 'weight', 'type', 'master_field', 'source_field', 'operator', 'matching_id', 'matching_rule_set_id', 'created_by_id', 'modified_by_id'])) {
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

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
