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
use Doctrine\DBAL\ParameterType;

class V2Dot3Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-02 18:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DELETE FROM attribute WHERE deleted = true");
            $this->exec("DROP INDEX idx_attribute_unique_code");
            $this->exec("ALTER TABLE attribute RENAME COLUMN code TO system_name");
            $this->exec("CREATE UNIQUE INDEX IDX_ATTRIBUTE_UNIQUE_ATTRIBUTE ON attribute (deleted, entity_id, system_name)");
        } else {
            $this->exec("DELETE FROM attribute WHERE deleted = 1");
            $this->exec("DROP INDEX IDX_ATTRIBUTE_UNIQUE_CODE ON attribute");
            $this->exec("ALTER TABLE attribute CHANGE code system_name VARCHAR(255) DEFAULT NULL");
            $this->exec("CREATE UNIQUE INDEX IDX_ATTRIBUTE_UNIQUE_ATTRIBUTE ON attribute (deleted, entity_id, system_name)");
        }

        $this->fillSystemNames();

        $this->createSourceToStagingPipelineTable();
        $this->migrateSourceEntities();
    }

    private function createSourceToStagingPipelineTable(): void
    {
        if ($this->isPgSQL()) {
            $this->exec(
                "CREATE TABLE source_to_staging_pipeline (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', staging_entity_id VARCHAR(36) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, merging_script TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX IDX_SOURCE_TO_STAGING_PIPELINE_UNIQUE_SOURCE_ENTITY ON source_to_staging_pipeline (deleted, source_entity)");
            $this->exec("CREATE INDEX IDX_SOURCE_TO_STAGING_PIPELINE_STAGING_ENTITY_ID ON source_to_staging_pipeline (staging_entity_id, deleted)");
            $this->exec("CREATE INDEX IDX_SOURCE_TO_STAGING_PIPELINE_CREATED_AT ON source_to_staging_pipeline (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_SOURCE_TO_STAGING_PIPELINE_CREATED_BY_ID ON source_to_staging_pipeline (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_SOURCE_TO_STAGING_PIPELINE_MODIFIED_BY_ID ON source_to_staging_pipeline (modified_by_id, deleted)");
        } else {
            $this->exec(
                "CREATE TABLE source_to_staging_pipeline (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', staging_entity_id VARCHAR(36) DEFAULT NULL, source_entity VARCHAR(255) DEFAULT NULL, merging_script LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_SOURCE_TO_STAGING_PIPELINE_UNIQUE_SOURCE_ENTITY (deleted, source_entity), INDEX IDX_SOURCE_TO_STAGING_PIPELINE_STAGING_ENTITY_ID (staging_entity_id, deleted), INDEX IDX_SOURCE_TO_STAGING_PIPELINE_CREATED_AT (created_at, deleted), INDEX IDX_SOURCE_TO_STAGING_PIPELINE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_SOURCE_TO_STAGING_PIPELINE_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
        }
    }

    private function migrateSourceEntities(): void
    {
        $rows = $this->getDbal()->createQueryBuilder()
            ->select('id', 'source_entity')
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
                        ->insert('source_to_staging_pipeline')
                        ->values([
                            'id'                    => ':id',
                            'deleted'               => ':false',
                            'staging_entity_id' => ':masterDataEntityId',
                            'source_entity'         => ':sourceEntity',
                            'merging_script'        => ':mergingScript',
                        ])
                        ->setParameter('id', IdGenerator::uuid())
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->setParameter('masterDataEntityId', $row['id'])
                        ->setParameter('sourceEntity', $sourceEntity)
                        ->setParameter('mergingScript', '{# { "stagingRecordData": { "name" : "{{ sourceRecord.name }}" } } #}')
                        ->executeStatement();
                } catch (\Throwable $e) {
                }
            }
        }
    }

    private function fillSystemNames(): void
    {
        $offset = 0;
        $limit  = 5000;

        $checkRules = [];
        $updateCheckRules = false;

        if (file_exists('data/reference-data/QualityCheckRule.json')) {
            $fileData = @json_decode(file_get_contents('data/reference-data/QualityCheckRule.json'), true);
            if (!empty($fileData) && is_array($fileData)) {
                $checkRules = $fileData;
            }
        }

        while (true) {
            $rows = $this->getDbal()->createQueryBuilder()
                ->select('id', 'name', 'system_name')
                ->from('attribute')
                ->orderBy('id')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $sn = (string)($row['system_name'] ?? '');

                if ($sn !== '' && $this->isValidSystemName($sn)) {
                    continue;
                }

                $source = $sn !== '' ? $sn : (string)($row['name'] ?? '');
                $base   = $this->sanitizeSystemName($source);
                if ($base === '') {
                    $base = 'attribute';
                }

                if ($base === $sn) {
                    continue;
                }

                $candidate = $base;
                $i         = 2;
                while (true) {
                    try {
                        $this->getDbal()->createQueryBuilder()
                            ->update('attribute')
                            ->set('system_name', ':systemName')
                            ->where('id = :id')
                            ->setParameter('systemName', $candidate)
                            ->setParameter('id', $row['id'])
                            ->executeStatement();

                        foreach ($checkRules as $k => $checkRule) {
                            if (empty($checkRule['attributeId']) || $checkRule['attributeId'] !== $row['id']) {
                                continue;
                            }

                            $code = empty($row['system_name']) ? $row['id'] : $row['system_name'];
                            $checkRules[$k]['entityField'] = str_replace($code, $candidate, $checkRule['entityField']);

                            $updateCheckRules = true;
                        }
                        break;
                    } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                        $candidate = $base . '_' . $i;
                        $i++;
                    } catch (\Throwable $e) {
                        break;
                    }
                }
            }

            $offset += $limit;
        }

        if ($updateCheckRules) {
            file_put_contents('data/reference-data/QualityCheckRule.json', json_encode($checkRules));
        }
    }

    private function isValidSystemName(string $name): bool
    {
        return preg_match('/^[a-z]([a-zA-Z0-9_]*[a-zA-Z0-9])?$/', $name) === 1;
    }

    private function sanitizeSystemName(string $input): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9_]/', '', $input);

        $noLeading = preg_replace('/^[^a-zA-Z]+/', '', (string)$cleaned);

        if ($noLeading === null || $noLeading === '') {
            return '';
        }

        $noTrailing = rtrim($noLeading, '_');

        if ($noTrailing === '') {
            return '';
        }

        return lcfirst($noTrailing);
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
