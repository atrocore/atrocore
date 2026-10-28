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
use Atro\Core\Utils\Util;

class V2Dot7Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-29 15:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('user')) {
            $table = $toSchema->getTable('user');

            if (!$table->hasColumn('is_data_privacy_admin')) {
                $table->addColumn('is_data_privacy_admin', 'boolean', ['default' => false, 'notnull' => true]);
            }
        }

        foreach ($this->getPersonalDataEntities() as $entityName) {
            $tableName = Util::toUnderScore(lcfirst($entityName));
            if (!$toSchema->hasTable($tableName)) {
                continue;
            }

            $table = $toSchema->getTable($tableName);

            if (!$table->hasColumn('erased_at')) {
                $table->addColumn('erased_at', 'datetime', ['notnull' => false]);
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    protected function getPersonalDataEntities(): array
    {
        $result = [];

        $dir = 'data/metadata/scopes';
        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (in_array($item, ['.', '..'])) {
                    continue;
                }

                $scope = explode('.', $item)[0];

                $content = @json_decode((string)file_get_contents($dir . '/' . $item), true);
                if (!empty($content['containsPersonalData']) && ($content['type'] ?? null) !== 'ReferenceData') {
                    $result[] = $scope;
                }
            }
        }

        return $result;
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
