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
use Atro\Core\Utils\Util;

class V2Dot4Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-24 18:00:00');
    }

    public function up(): void
    {
        $inputLanguageList = $this->getConfig()->get('inputLanguageList', []);
        if (empty($inputLanguageList)) {
            return;
        }

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $multilangCodeFields = [
            'Attribute' => 'script',
        ];

        foreach ($multilangCodeFields as $scope => $field) {
            $tableName = Util::toUnderScore(lcfirst($scope));

            if (!$toSchema->hasTable($tableName)) {
                continue;
            }
            $table = $toSchema->getTable($tableName);

            $baseColumn = Util::toUnderScore(lcfirst($field));

            if ($table->hasColumn($baseColumn)) {
                foreach ($inputLanguageList as $code) {
                    $column = $baseColumn . '_' . strtolower($code);

                    if (!$table->hasColumn($column)) {
                        $table->addColumn($column, 'text', ['notnull' => false]);
                    }
                }
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}