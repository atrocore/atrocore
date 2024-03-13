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
use Doctrine\DBAL\ParameterType;

class V1Dot8Dot29 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->hasTable('product')) {
            $table = $toSchema->createTable('product');
            $table->addColumn('id', 'string', ['length' => 24]);
            $table->addColumn('name', 'string', ['notnull' => false]);
            $table->addColumn('long_description', 'text', ['notnull' => false]);
            if ($this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $locale = strtolower($locale);

                    $table->addColumn("name_$locale", 'string', ['notnull' => false]);
                    $table->addColumn("long_description_$locale", 'text', ['notnull' => false]);
                }
            }
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('created_by_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('sort_order', 'integer', ['notnull' => false]);
            $table->addColumn('sku', 'string', ['notnull' => false]);
            $table->addColumn('price', 'float', ['notnull' => false]);
            $table->addColumn('price_unit_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('image_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('is_active', 'boolean', ['default' => false]);
            $table->addColumn('deleted', 'boolean', ['default' => false]);
            $table->setPrimaryKey(['id']);
        } else {
            $table = $toSchema->getTable('product');
            if (!$table->hasColumn('image_id')) {
                $table->addColumn('image_id', 'string', ['notnull' => false, 'length' => 24]);
            }
        }

        if (!$toSchema->hasTable('product_hierarchy')) {
            $table = $toSchema->createTable('product_hierarchy');
            $table->addColumn('id', 'string', ['length' => 24]);
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('created_by_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('parent_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('entity_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('hierarchy_sort_order', 'integer', ['notnull' => false]);
            $table->addColumn('deleted', 'boolean', ['default' => false]);
            $table->setPrimaryKey(['id']);
        }


        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
