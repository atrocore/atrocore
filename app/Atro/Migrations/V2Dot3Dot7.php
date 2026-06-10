<?php
/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\ParameterType;

class V2Dot3Dot7 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-10 12:00:00');
    }

    public function up(): void
    {
        $this->createTable();
        $this->migrateSourceEntities();
    }

    private function createTable(): void
    {
        if ($this->isPgSQL()) {
            $this->exec(
                "CREATE TABLE master_data_entity_source (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', master_data_entity_id VARCHAR(36) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, merging_script TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX IDX_MASTER_DATA_ENTITY_SOURCE_UNIQUE_SOURCE_ENTITY ON master_data_entity_source (deleted, source_entity)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_SOURCE_MASTER_DATA_ENTITY_ID ON master_data_entity_source (master_data_entity_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_SOURCE_CREATED_AT ON master_data_entity_source (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_SOURCE_CREATED_BY_ID ON master_data_entity_source (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MASTER_DATA_ENTITY_SOURCE_MODIFIED_BY_ID ON master_data_entity_source (modified_by_id, deleted)");
        } else {
            $this->exec(
                "CREATE TABLE master_data_entity_source (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', master_data_entity_id VARCHAR(36) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, merging_script LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_MASTER_DATA_ENTITY_SOURCE_UNIQUE_SOURCE_ENTITY (deleted, source_entity), INDEX IDX_MASTER_DATA_ENTITY_SOURCE_MASTER_DATA_ENTITY_ID (master_data_entity_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_SOURCE_CREATED_AT (created_at, deleted), INDEX IDX_MASTER_DATA_ENTITY_SOURCE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MASTER_DATA_ENTITY_SOURCE_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
        }
    }

    private function migrateSourceEntities(): void
    {
        $rows = $this->getDbal()->createQueryBuilder()
            ->select('id', 'source_entity', 'merging_script')
            ->from('master_data_entity')
            ->where('deleted = :false')
            ->andWhere('source_entity IS NOT NULL')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $sourceEntities = json_decode($row['source_entity'] ?? '[]', true);
            if (empty($sourceEntities) || !is_array($sourceEntities)) {
                continue;
            }

            foreach ($sourceEntities as $sourceEntity) {
                if (empty($sourceEntity)) {
                    continue;
                }

                try {
                    $this->getDbal()->createQueryBuilder()
                        ->insert('master_data_entity_source')
                        ->values([
                            'id'                    => ':id',
                            'deleted'               => ':false',
                            'master_data_entity_id' => ':masterDataEntityId',
                            'source_entity'         => ':sourceEntity',
                            'merging_script'        => ':mergingScript',
                        ])
                        ->setParameter('id', IdGenerator::uuid())
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->setParameter('masterDataEntityId', $row['id'])
                        ->setParameter('sourceEntity', $sourceEntity)
                        ->setParameter('mergingScript', $row['merging_script'])
                        ->executeStatement();
                } catch (\Throwable $e) {
                }
            }
        }
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
