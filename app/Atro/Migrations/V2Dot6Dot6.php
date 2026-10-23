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
use Doctrine\DBAL\Schema\Table;

class V2Dot6Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-23 12:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        // the vat_id column already exists where the Accounting module is installed
        if ($toSchema->hasTable('account')) {
            $table = $toSchema->getTable('account');
            foreach (['vat_id', 'web_url'] as $column) {
                if (!$table->hasColumn($column)) {
                    $table->addColumn($column, 'string', ['length' => 255, 'notnull' => false]);
                }
            }
        }

        if ($toSchema->hasTable('product') && $toSchema->getTable('product')->hasColumn('gtin')) {
            $this->ensureIndex($toSchema->getTable('product'), 'IDX_PRODUCT_GTIN', ['gtin', 'deleted'], false);
        }

        if ($toSchema->hasTable('brand')) {
            $table = $toSchema->getTable('brand');

            foreach ($this->getConfig()->get('inputLanguageList', []) as $code) {
                $language = strtolower($code);

                if (!$table->hasColumn("name_$language")) {
                    $table->addColumn("name_$language", 'string', ['length' => 255, 'notnull' => false]);
                }
                if (!$table->hasColumn("description_$language")) {
                    $table->addColumn("description_$language", 'text', ['notnull' => false]);
                }
            }

            if (!$table->columnsAreIndexed(['code', 'deleted'])) {
                $table->addUniqueIndex(['code', 'deleted']);
            }
        }

        // the relation tables created by the PIM module have the unique indexes with another column order
        if ($toSchema->hasTable('brand_file')) {
            $this->ensureIndex($toSchema->getTable('brand_file'), 'IDX_BRAND_FILE_UNIQUE_RELATION', ['deleted', 'brand_id', 'file_id'], true);
        }

        if ($toSchema->hasTable('user_followed_brand')) {
            $this->ensureIndex($toSchema->getTable('user_followed_brand'), 'IDX_USER_FOLLOWED_BRAND_UNIQUE_RELATION', ['deleted', 'brand_id', 'user_id'], true);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    protected function ensureIndex(Table $table, string $name, array $columns, bool $unique): void
    {
        if ($table->hasIndex($name)) {
            $index = $table->getIndex($name);
            $existingColumns = array_map('strtolower', $index->getColumns());

            if ($existingColumns === $columns && $index->isUnique() === $unique) {
                return;
            }

            $table->dropIndex($name);
        }

        if ($unique) {
            $table->addUniqueIndex($columns, $name);
        } else {
            $table->addIndex($columns, $name);
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
