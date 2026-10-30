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
use Doctrine\DBAL\ParameterType;

class V2Dot7Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-29 12:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE cluster ADD consolidation_method VARCHAR(255) DEFAULT NULL");
        $this->exec("ALTER TABLE cluster ADD execution_mode VARCHAR(255) DEFAULT NULL");

        $this->getDbal()->createQueryBuilder()
            ->update('cluster')
            ->set('consolidation_method', ':ruleBased')
            ->set(
                'execution_mode',
                "CASE WHEN (SELECT ci.consolidated_automatically FROM cluster_item ci WHERE ci.cluster_id = cluster.id AND ci.entity_name <> cluster.master_entity AND ci.deleted = :false ORDER BY ci.modified_at DESC LIMIT 1) = :true THEN :automatic ELSE :manual END"
            )
            ->where('golden_record_id IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('ruleBased', 'ruleBased')
            ->setParameter('automatic', 'automatic')
            ->setParameter('manual', 'manual')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeStatement();

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE cluster_item RENAME COLUMN consolidated_automatically TO confirmed_automatically");
        } else {
            $this->exec("ALTER TABLE cluster_item CHANGE consolidated_automatically confirmed_automatically TINYINT(1) DEFAULT '0' NOT NULL");
        }

        $this->migrateSavedSearches();
    }

    protected function migrateSavedSearches(): void
    {
        $savedSearches = $this->getDbal()->createQueryBuilder()
            ->select('id', 'data')
            ->from('saved_search')
            ->where('entity_type = :entityType')
            ->andWhere('deleted = :false')
            ->setParameter('entityType', 'Cluster')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($savedSearches as $savedSearch) {
            $data = @json_decode((string)$savedSearch['data'], true);
            if (!is_array($data)) {
                continue;
            }

            $migrated = $this->migrateRules($data);
            if ($migrated === $data) {
                continue;
            }

            $this->getDbal()->createQueryBuilder()
                ->update('saved_search')
                ->set('data', ':data')
                ->where('id = :id')
                ->setParameter('data', json_encode($migrated))
                ->setParameter('id', $savedSearch['id'])
                ->executeStatement();
        }
    }

    protected function migrateRules(array $group): array
    {
        if (empty($group['rules']) || !is_array($group['rules'])) {
            return $group;
        }

        foreach ($group['rules'] as $k => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (isset($rule['rules'])) {
                $group['rules'][$k] = $this->migrateRules($rule);
                continue;
            }

            if (($rule['field'] ?? $rule['id'] ?? null) !== 'state') {
                continue;
            }

            $rule['id'] = 'status';
            $rule['field'] = 'status';

            $values = is_array($rule['value'] ?? null) ? $rule['value'] : null;
            if ($values === null || !in_array('consolidatedAutomatically', $values)) {
                $group['rules'][$k] = $rule;
                continue;
            }

            if (($rule['operator'] ?? null) === 'in' && $values === ['consolidatedAutomatically']) {
                $executionModeRule = array_merge($rule, ['id' => 'executionMode', 'field' => 'executionMode', 'value' => ['automatic']]);
                $rule['value'] = ['consolidated'];

                $group['rules'][$k] = [
                    'condition' => 'AND',
                    'rules'     => [$rule, $executionModeRule],
                    'valid'     => true,
                ];
                continue;
            }

            $values = array_map(fn($value) => $value === 'consolidatedAutomatically' ? 'consolidated' : $value, $values);
            $rule['value'] = array_values(array_unique($values));

            $group['rules'][$k] = $rule;
        }

        return $group;
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
