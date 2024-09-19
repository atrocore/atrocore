<?php
/**
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
use Atro\Core\Utils\Util;

class V1Dot8Dot3 extends Base
{
    public function up(): void
    {
    }

    public function down(): void
    {
    }

    public static function migrateCurrencyField(Base $migration, string $entity, string $field, string $type = "currency")
    {
        $units = $migration->getConnection()->createQueryBuilder()
            ->select(['name', 'id'])
            ->from('unit')
            ->where('measure_id=:id')
            ->setParameter('id', 'currency')
            ->fetchAllKeyValue();

        $table = Util::toUnderScore($entity);
        $unitField = Util::toUnderScore($field . 'UnitId');
        $currencyField = Util::toUnderScore($type === 'currency' ? $field . 'Currency' : $field . "FromCurrency");

        $fromSchema = $migration->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $migration->addColumn($toSchema, $table, $unitField, ['type' => 'string', 'default' => null]);

        foreach ($migration->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $migration->getPDO()->exec($sql);
        }

        // migrate currency data
        $limit = 2000;
        $offset = 0;

        while (true) {

            $entities = $migration->getConnection()->createQueryBuilder()
                ->from($table)
                ->select(['id', $currencyField])
                ->where("$currencyField is not null")
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->fetchAllAssociative();

            if (empty($entities)) {
                break;
            }

            $offset = $offset + $limit;

            foreach ($entities as $entity) {
                $unitId = $units[$entity[$currencyField]] ?? $entity[$currencyField];

                $migration->getConnection()->createQueryBuilder()
                    ->update($table)
                    ->set($unitField, ':value')
                    ->where('id = :id')
                    ->setParameter('id', $entity['id'])
                    ->setParameter('value', $unitId)
                    ->executeStatement();
            }
        }
        // remove currency column
        $fromSchema = $migration->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $migration->dropColumn($toSchema, $table, $currencyField);
        if ($type === 'rangeCurrency') {
            $migration->dropColumn($toSchema, $table, Util::toUnderScore($field . "ToCurrency"));
        }

        foreach ($migration->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $migration->getPDO()->exec($sql);
        }

        if ($type === 'rangeCurrency') {
            return;
        }
        // change currency field in layouts
        $dir = "custom/Espo/Custom/Resources/layouts/$entity";
        $files = scandir($dir);
        foreach ($files as $file) {
            if (in_array($file, array(".", ".."))) {
                continue;
            }
            $path = "$dir/$file";
            if (file_exists($path)) {
                $contents = file_get_contents($path);
                $contents = str_replace("\"$field\"", '"unit' . lcfirst($field) . '"', $contents);
                file_put_contents($path, $contents);
            }
        }
    }
}
