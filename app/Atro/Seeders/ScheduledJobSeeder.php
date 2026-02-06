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
                    'id'             => '019c320c-3b3c-72f3-bafc-5611287c8dd0',
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
                    'id'             => '019c320c-5fc0-7186-8a68-099bcbc02c39',
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
                    'id'             => '019c320c-814b-71dc-8905-b26630c53a79',
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
                    'id'             => '019c320c-af2a-701d-9218-594437574559',
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
                        'id'             => '019c320c-d70c-723c-bf09-9497b2be355c',
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
                        'id'             => '019c320d-0e6b-719b-87b0-c09443372da3',
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