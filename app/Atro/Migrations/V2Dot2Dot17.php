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
        $entities = [];

        if (class_exists("\\Pim\\Module")) {
            $entities = ['Product', 'Listing'];
        }

        if (class_exists("\\Components\\Module")) {
            $entities[] = 'Component';
        }

        $dir = 'data/metadata/scopes';
        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $parts = explode('.', $item);
                    $scope = $parts[0];

                    $content = @json_decode(file_get_contents($dir . '/' . $item), true);
                    if (empty($content)) {
                        continue;
                    }

                    if (!empty($content['hasAttribute'])) {
                        $entities[] = $scope;
                        continue;
                    }

                    if (!empty($content['primaryEntityId']) && in_array($content['primaryEntityId'], $entities)) {
                        $entities[] = $scope;
                    }
                }
            }
        }

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($entities as $targetEntity) {
            $avEntity = $targetEntity . 'AttributeValue';
            $fieldName = lcfirst($targetEntity) . 'Id';

            $tableName = Util::toUnderScore(lcfirst($avEntity));
            if (!$toSchema->hasTable($tableName)) {
                continue;
            }

            $this->cleanAttributeValues($targetEntity);

            $table = $toSchema->getTable($tableName);
            $table->addForeignKeyConstraint(Util::toUnderScore(lcfirst($targetEntity)), [Util::toUnderScore($fieldName)], ['id'], ['onDelete' => 'CASCADE'], Converter::generateForeignKeyName($avEntity, $fieldName));
            $table->addIndex([Util::toUnderScore($fieldName)], Converter::generateForeignKeyName($avEntity, $fieldName));

            $table->addForeignKeyConstraint('attribute', ['attribute_id'], ['id'], ['onDelete' => 'CASCADE'], Converter::generateForeignKeyName($avEntity, 'attributeId'));
            $table->addIndex(['attribute_id'], Converter::generateForeignKeyName($avEntity, 'attributeId'));
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
