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
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot17 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-09 11:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        foreach ($metadata->get('scopes') ?? [] as $scope => $scopeDefs) {
            $tableName = Util::toUnderScore(lcfirst($scope));

            if (empty($scopeDefs['attributeValueFor'])) {
                continue;
            }

            $targetEntity = $scopeDefs['attributeValueFor'];
            $fieldName = lcfirst($targetEntity) . 'Id';
            $targetTableName = Util::toUnderScore(lcfirst($targetEntity));

            if (!$toSchema->hasTable($tableName)) {
                continue;
            }

            $this->cleanAttributeValues($targetEntity);

            $table = $toSchema->getTable($tableName);
            $table->addForeignKeyConstraint($targetTableName, [Util::toUnderScore($fieldName)], ['id'], ['onDelete' => 'CASCADE'], Converter::generateForeignKeyName($scope, $fieldName));
            $table->addForeignKeyConstraint('attribute', ['attribute_id'], ['id'], ['onDelete' => 'CASCADE'], Converter::generateForeignKeyName($scope, 'attributeId'));

            $table->addIndex([Util::toUnderScore($fieldName)], Converter::generateForeignKeyName($scope, $fieldName));
            $table->addIndex(['attribute_id'], Converter::generateForeignKeyName($scope, 'attributeId'));
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    private function cleanAttributeValues(string $entityName): void
    {
        $name = Util::toUnderScore(lcfirst($entityName));

        while (true) {
            $ids = $this->getConnection()->createQueryBuilder()
                ->select('av.id')
                ->from("{$name}_attribute_value", 'av')
                ->leftJoin('av', $name, 'e', "e.id=av.{$name}_id")
                ->leftJoin('av', $this->getConnection()->quoteIdentifier('attribute'), 'a', "a.id=av.attribute_id")
                ->where('e.id IS NULL OR a.id IS NULL')
                ->setFirstResult(0)
                ->setMaxResults(20000)
                ->fetchFirstColumn();

            if (empty($ids)) {
                break;
            }

            $this->getConnection()->createQueryBuilder()
                ->delete("{$name}_attribute_value")
                ->where('id IN (:ids)')
                ->setParameter('ids', $ids, $this->getConnection()::PARAM_STR_ARRAY)
                ->executeStatement();
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
            $GLOBALS['log']->error($e->getMessage());
        }
    }
}
