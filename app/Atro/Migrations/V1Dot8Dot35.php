<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot8Dot35 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $table = $toSchema->getTable('product');
        if (!$table->hasColumn('quantity')) {
            if ($table->hasColumn('amount')) {
                $this->execute("alter table product rename column amount to quantity;");
            } else {
                $table->addColumn('quantity', 'float', ['notnull' => false]);
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->execute($sql);
        }

        // Migrate layouts
        $path = "custom/Espo/Custom/Resources/layouts/Product";
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                $filePath = "$path/$file";
                if (!is_file($filePath)) {
                    continue;
                }

                $contents = file_get_contents($filePath);
                $contents = str_replace('"amount"', '"quantity"', $contents);
                file_put_contents($filePath, $contents);
            }
        }

        $this->updateComposer('atrocore/core', '^1.8.35');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }

    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
