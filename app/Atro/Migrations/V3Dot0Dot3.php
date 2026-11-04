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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\Types\Type;

class V3Dot0Dot3 extends Base
{
    /**
     * The columns of the fields of type "jsonArray" that become "jsonObject". Besides them, the "routes" of all
     * hierarchies are changed, also of the custom ones.
     */
    protected const COLUMNS = [
        'attribute_type_enum'           => ['conditional_disable_options'],
        'attribute_type_multi_enum'     => ['conditional_disable_options'],
        'attribute_type_link'           => ['conditional_disable_options'],
        'attribute_type_link_multiple'  => ['conditional_disable_options'],
        'classification_attribute'      => ['conditional_disable_options'],
        'layout_profile'                => ['navigation', 'dashboard_layout'],
        'user'                          => ['dashboard_layout'],
    ];

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-04 12:00:00');
    }

    /**
     * The field type "jsonArray" is removed, a JSON value of any structure is "jsonObject". The values and the SQL
     * types of the columns are the same, only the type of the column kept in its comment is changed.
     */
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($toSchema->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                if ($column->getType()->getName() !== 'jsonArray') {
                    continue;
                }

                $columnName = $column->getName();
                if ($columnName !== 'routes' && !in_array($columnName, self::COLUMNS[$table->getName()] ?? [], true)) {
                    continue;
                }

                $column->setType(Type::getType('jsonObject'));
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
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
