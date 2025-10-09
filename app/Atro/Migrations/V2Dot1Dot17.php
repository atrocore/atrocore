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
use Atro\ORM\DB\RDB\Mapper;

class V2Dot1Dot17 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-26 08:00:00');
    }

    public function up(): void
    {
        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');
        foreach ($metadata->get('scopes') ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['isHierarchyEntity'])) {
                $fromSchema = $this->getCurrentSchema();
                $toSchema = clone $fromSchema;
                $tableName = Util::toUnderScore($scope);
                if ($toSchema->hasTable($tableName)) {
                    $table = $toSchema->getTable($tableName);
                    $field = 'hierarchySortOrder';
                    if (!$table->hasColumn(util::toUnderScore($field))) {
                        $table->addColumn(Util::toUnderScore($field), 'integer', ['notnull' => false]);
                    }
                }
                foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                    $this->exec($sql);
                }
            }
        }

        // add default scheduled job
        $data = [
            'id'             => 'CalculateScriptFieldsForEntities',
            'name'           => 'Calculate script fields',
            'type'           => 'CalculateScriptFieldsForEntities',
            'is_active'      => true,
            'scheduling'     => '0 3 * * *',
            'created_at'     => date('Y-m-d H:i:s'),
            'modified_at'    => date('Y-m-d H:i:s'),
            'created_by_id'  => 'system',
            'modified_by_id' => 'system',
        ];


        $qb = $this->getConnection()->createQueryBuilder()
            ->insert($this->getConnection()->quoteIdentifier('scheduled_job'));

        foreach ($data as $columnName => $value) {
            $qb->setValue($columnName, ":$columnName");
            $qb->setParameter($columnName, $value, Mapper::getParameterType($value));
        }

        $qb->executeQuery();
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
