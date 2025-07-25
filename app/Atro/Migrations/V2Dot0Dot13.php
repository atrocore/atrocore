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
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V2Dot0Dot13 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-07-10 15:00:00');
    }

    public function up(): void
    {
        if ($this->getCurrentSchema()->hasTable('association')) {
            $this->exec("ALTER TABLE association ADD entity_id VARCHAR(36) DEFAULT NULL");


            if ($this->isPgSQL()) {
                $this->exec("DROP INDEX idx_association_backward_association_id;");
                $this->exec("ALTER TABLE association RENAME COLUMN backward_association_id TO reverse_association_id;");
                $this->exec("CREATE INDEX IDX_ASSOCIATION_REVERSE_ASSOCIATION_ID ON association (reverse_association_id, deleted);");
                $this->exec("ALTER TABLE association ADD \"default\" BOOLEAN DEFAULT 'false' NOT NULL");
            } else {
                $this->exec("DROP INDEX IDX_ASSOCIATION_BACKWARD_ASSOCIATION_ID ON association;");
                $this->exec("ALTER TABLE association CHANGE backward_association_id reverse_association_id VARCHAR(36) DEFAULT NULL;");
                $this->exec("CREATE INDEX IDX_ASSOCIATION_REVERSE_ASSOCIATION_ID ON association (reverse_association_id, deleted);");
                $this->exec("ALTER TABLE association ADD `default` TINYINT(1) DEFAULT '0' NOT NULL;");
            }
            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('association'))
                ->set('entity_id', ':entity_id')
                ->where('deleted=:false')
                ->andWhere('entity_id is null')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('entity_id', 'Product')
                ->executeStatement();
        } else {
            if ($this->isPgSQL()) {
                $this->exec("CREATE TABLE association (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', \"default\" BOOLEAN DEFAULT 'false' NOT NULL, code VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, entity_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, reverse_association_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));");
                $this->exec("CREATE UNIQUE INDEX UNIQ_FD8521CC77153098EB3B4E33 ON association (code, deleted);");
                $this->exec("CREATE INDEX IDX_ASSOCIATION_CREATED_BY_ID ON association (created_by_id, deleted);");
                $this->exec("CREATE INDEX IDX_ASSOCIATION_MODIFIED_BY_ID ON association (modified_by_id, deleted);");
                $this->exec("CREATE INDEX IDX_ASSOCIATION_OWNER_USER_ID ON association (owner_user_id, deleted);");
                $this->exec("CREATE INDEX IDX_ASSOCIATION_ASSIGNED_USER_ID ON association (assigned_user_id, deleted);");
                $this->exec("CREATE INDEX IDX_ASSOCIATION_REVERSE_ASSOCIATION_ID ON association (reverse_association_id, deleted);");
            } else {
                $this->exec("CREATE TABLE association (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', `default` TINYINT(1) DEFAULT '0' NOT NULL, code VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, entity_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, reverse_association_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_FD8521CC77153098EB3B4E33 (code, deleted), INDEX IDX_ASSOCIATION_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_ASSOCIATION_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_ASSOCIATION_OWNER_USER_ID (owner_user_id, deleted), INDEX IDX_ASSOCIATION_ASSIGNED_USER_ID (assigned_user_id, deleted), INDEX IDX_ASSOCIATION_REVERSE_ASSOCIATION_ID (reverse_association_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            }

            if (!empty($this->getConfig()->get('isMultilangActive'))) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                    $language = strtolower($language);
                    $this->exec("ALTER TABLE association ADD name_$language VARCHAR(255) DEFAULT NULL");
                    if ($this->isPgSQL()) {
                        $this->exec("ALTER TABLE association ADD description_$language TEXT DEFAULT NULL");
                    } else {
                        $this->exec("ALTER TABLE association ADD description_$language LONGTEXT DEFAULT NULL");
                    }
                }
            }
        }

        // migrate association tables
        foreach ($this->getCurrentSchema()->getTables() as $table) {
            $tableName = $table->getName();
            $parts = explode('_', $tableName);
            if (count($parts) < 2 || $parts[0] !== 'associated') {
                continue;
            }
            array_shift($parts);
            $mainTableName = join('_', $parts);

            $leftColumn = "main_{$mainTableName}_id";
            $rightColumn = "related_{$mainTableName}_id";

            $upperTableName = strtoupper($tableName);


            if ($table->hasColumn($leftColumn) && $table->hasColumn($rightColumn)) {
                if ($this->isPgSQL()) {
                    $this->exec("DROP INDEX IDX_{$upperTableName}_UNIQUE_RELATION;");
                    $this->exec("DROP INDEX idx_{$tableName}_backward_{$tableName}_id;");
                    $this->exec("DROP INDEX idx_{$tableName}_related_{$mainTableName}_id;");
                    $this->exec("DROP INDEX idx_{$tableName}_main_{$mainTableName}_id;");

                    $this->exec("ALTER TABLE $tableName RENAME COLUMN $leftColumn TO associating_item_id;");
                    $this->exec("ALTER TABLE $tableName RENAME COLUMN $rightColumn TO associated_item_id;");
                    $this->exec("ALTER TABLE $tableName RENAME COLUMN backward_{$tableName}_id TO reverse_{$tableName}_id;");
                } else {
                    $this->exec("DROP INDEX IDX_{$upperTableName}_UNIQUE_RELATION ON $tableName");
                    $this->exec("DROP INDEX idx_{$tableName}_backward_{$tableName}_id ON $tableName;");
                    $this->exec("DROP INDEX idx_{$tableName}_related_{$mainTableName}_id ON $tableName;");
                    $this->exec("DROP INDEX idx_{$tableName}_main_{$mainTableName}_id ON $tableName;");

                    $this->exec("ALTER TABLE $tableName CHANGE $leftColumn associating_item_id VARCHAR(36) DEFAULT NULL;");
                    $this->exec("ALTER TABLE $tableName CHANGE $rightColumn associated_item_id VARCHAR(36) DEFAULT NULL;");
                    $this->exec("ALTER TABLE $tableName CHANGE backward_{$tableName}_id reverse_{$tableName}_id VARCHAR(36) DEFAULT NULL;");
                }

                $this->exec("CREATE INDEX IDX_{$upperTableName}_ASSOCIATING_ITEM_ID ON $tableName (associating_item_id, deleted);");
                $this->exec("CREATE INDEX IDX_{$upperTableName}_ASSOCIATED_ITEM_ID ON $tableName (associated_item_id, deleted);");
                $this->exec("CREATE INDEX IDX_{$upperTableName}_REVERSE_{$upperTableName}_ID ON $tableName (reverse_{$tableName}_id, deleted);");
                $this->exec("CREATE UNIQUE INDEX IDX_{$upperTableName}_UNIQUE_RELATION ON $tableName (deleted, association_id, associating_item_id, associated_item_id);");
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
