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

class V2Dot0Dot25 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-04 12:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('product')) {
            $table = $toSchema->getTable('product');

            if ($table->hasIndex('IDX_PRODUCT_SKU')) {
                $table->dropIndex('IDX_PRODUCT_SKU');
            }

            if (!$table->hasColumn('description')) {
                $table->addColumn('description', 'text', ['notnull' => false]);

                if (!empty($this->getConfig()->get('isMultilangActive'))) {
                    foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                        $table->addColumn('description_' . strtolower($language), 'text', ['notnull' => false]);
                    }
                }
            }

            if ($table->hasColumn('sku')) {
                if ($this->isPgSQL()) {
                    $this->exec("ALTER TABLE product RENAME COLUMN sku TO number;");
                } else {
                    $this->exec("ALTER TABLE product CHANGE sku number VARCHAR(255) DEFAULT NULL;");
                }
                $this->exec("CREATE INDEX IDX_PRODUCT_NUMBER ON product (number, deleted);");
            }

            if (!$table->hasColumn('mpn')) {
                $table->addColumn('mpn', 'string', ['length' => 255, 'notnull' => false]);

                $table->addIndex(['mpn', 'deleted'], 'IDX_PRODUCT_MPN');
            }

            if (!$table->hasColumn('customs_number')) {
                $table->addColumn('customs_number', 'string', ['length' => 16, 'notnull' => false]);
            }

            if (!$table->hasColumn('note')) {
                $table->addColumn('note', 'text', ['notnull' => false]);
            }

            if (!$table->hasColumn('country_of_origin_id')) {
                $table->addColumn('country_of_origin_id', 'string', ['length' => 36, 'notnull' => false]);
                $table->addIndex(['country_of_origin_id', 'deleted'], 'IDX_PRODUCT_COUNTRY_OF_ORIGIN_ID');
            }

            if (!$table->hasColumn('default_supplier_id')) {
                $table->addColumn('default_supplier_id', 'string', ['length' => 36, 'notnull' => false]);
                $table->addIndex(['default_supplier_id', 'deleted'], 'IDX_PRODUCT_DEFAULT_SUPPLIER_ID');
            }

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->exec($sql);
            }
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
