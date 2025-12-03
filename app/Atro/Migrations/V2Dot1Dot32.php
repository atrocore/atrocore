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

class V2Dot1Dot32 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-12-02 14:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE matched_record ADD matching_id VARCHAR(36) DEFAULT NULL");

        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE master_data_entity (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', mapping_script TEXT DEFAULT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_CREATED_BY_ID ON master_data_entity (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_MODIFIED_BY_ID ON master_data_entity (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_OWNER_USER_ID ON master_data_entity (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_ASSIGNED_USER_ID ON master_data_entity (assigned_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_CREATED_AT ON master_data_entity (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_MODIFIED_AT ON master_data_entity (modified_at, deleted)");

            $this->exec("CREATE TABLE user_followed_master_data_entity (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, master_data_entity_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_UNIQUE_RELATION ON user_followed_master_data_entity (deleted, master_data_entity_id, user_id)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_CREATED_BY_ID ON user_followed_master_data_entity (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MODIFIED_BY_ID ON user_followed_master_data_entity (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MASTER_DATA_ENTITY_ID ON user_followed_master_data_entity (master_data_entity_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_USER_ID ON user_followed_master_data_entity (user_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_CREATED_AT ON user_followed_master_data_entity (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MODIFIED_AT ON user_followed_master_data_entity (modified_at, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MATCHING_ID ON matched_record (matching_id, deleted)");
        } else {
            $this->exec("CREATE TABLE master_data_entity (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', mapping_script LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, INDEX IDX_MASTER_DATA_ENTITY_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_OWNER_USER_ID (owner_user_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_ASSIGNED_USER_ID (assigned_user_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_CREATED_AT (created_at, deleted), INDEX IDX_MASTER_DATA_ENTITY_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE user_followed_master_data_entity (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, master_data_entity_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_UNIQUE_RELATION (deleted, master_data_entity_id, user_id), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MASTER_DATA_ENTITY_ID (master_data_entity_id, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_MASTER_DATA_ENTITY_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
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

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
