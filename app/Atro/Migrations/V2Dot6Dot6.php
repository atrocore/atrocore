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

        // brand_file created by the PIM module has no unique relation index
        if ($toSchema->hasTable('brand_file')) {
            $table = $toSchema->getTable('brand_file');
            if (!$table->hasIndex('IDX_BRAND_FILE_UNIQUE_RELATION')) {
                $table->addUniqueIndex(['deleted', 'brand_id', 'file_id'], 'IDX_BRAND_FILE_UNIQUE_RELATION');
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
