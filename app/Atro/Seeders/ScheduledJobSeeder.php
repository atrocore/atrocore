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

namespace Atro\Seeders;

use Atro\Core\Utils\IdGenerator;
use Atro\ORM\DB\RDB\Mapper;

class ScheduledJobSeeder extends AbstractSeeder
{
    public function run(): void
    {
        $toInsertRecords = [
            [
                'tableName' => 'scheduled_job',
                'data'      => [
                    'id'             => IdGenerator::toUuid('ComposerAutoUpdate'),
                    'name'           => 'Update system automatically',
                    'type'           => 'ComposerAutoUpdate',
                    'is_active'      => false,
                    'scheduling'     => '0 0 * * SUN',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'modified_at'    => date('Y-m-d H:i:s'),
                    'created_by_id'  => 'system',
                    'modified_by_id' => 'system',
                ],
            ],
            [
                'tableName' => 'scheduled_job',
                'data'      => [
                    'id'             => IdGenerator::toUuid('UpdateCurrencyExchangeViaECB'),
                    'name'           => 'Update currency exchange via ECB',
                    'type'           => 'UpdateCurrencyExchangeViaECB',
                    'is_active'      => true,
                    'scheduling'     => '0 2 * * *',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'modified_at'    => date('Y-m-d H:i:s'),
                    'created_by_id'  => 'system',
                    'modified_by_id' => 'system',
                ],
            ],
            [
                'tableName' => 'scheduled_job',
                'data'      => [
                    'id'             => IdGenerator::toUuid('ClearEntities'),
                    'name'           => 'Clear deleted data',
                    'type'           => 'ClearEntities',
                    'is_active'      => true,
                    'scheduling'     => '0 2 1 * *',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'modified_at'    => date('Y-m-d H:i:s'),
                    'created_by_id'  => 'system',
                    'modified_by_id' => 'system',
                ],
            ],
            [
                'tableName' => 'scheduled_job',
                'data'      => [
                    'id'             => IdGenerator::toUuid('CheckUpdates'),
                    'name'           => 'Check system updates',
                    'type'           => 'CheckUpdates',
                    'is_active'      => true,
                    'scheduling'     => '0 2 * * *',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'modified_at'    => date('Y-m-d H:i:s'),
                    'created_by_id'  => 'system',
                    'modified_by_id' => 'system',
                ],
                [
                    'tableName' => 'scheduled_job',
                    'data'      => [
                        'id'             => 'RecalculateScriptFieldForEntities',
                        'name'           => 'Calculate script fields',
                        'type'           => 'RecalculateScriptFieldForEntities',
                        'is_active'      => true,
                        'scheduling'     => '0 3 * * *',
                        'created_at'     => date('Y-m-d H:i:s'),
                        'modified_at'    => date('Y-m-d H:i:s'),
                        'created_by_id'  => 'system',
                        'modified_by_id' => 'system',
                    ],
                ],
                [
                    'tableName' => 'scheduled_job',
                    'data'      => [
                        'id'             => 'FindMatches',
                        'name'           => 'Find matches',
                        'type'           => 'FindMatches',
                        'is_active'      => true,
                        'scheduling'     => '0 */2 * * *',
                        'created_at'     => date('Y-m-d H:i:s'),
                        'modified_at'    => date('Y-m-d H:i:s'),
                        'created_by_id'  => 'system',
                        'modified_by_id' => 'system',
                    ],
                ],
            ],
        ];

        foreach ($toInsertRecords as $row) {
            $qb = $this->getConnection()->createQueryBuilder();
            $qb->insert($this->getConnection()->quoteIdentifier($row['tableName']));

            foreach ($row['data'] as $columnName => $value) {
                $qb->setValue($columnName, ":$columnName");
                $qb->setParameter($columnName, $value, Mapper::getParameterType($value));
            }

            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }
}