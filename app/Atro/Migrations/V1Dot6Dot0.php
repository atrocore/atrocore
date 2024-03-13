<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

namespace Atro\Migrations;

use Espo\Core\Utils\Util;
use Atro\Core\Migration\Base;

class V1Dot6Dot0 extends Base
{
    protected array $measureUnits = [];

    public function up(): void
    {
        $this->exec("DROP INDEX UNIQ_6598AC4577153098EB3B4E33 ON extensible_enum_option");
        $this->exec("CREATE UNIQUE INDEX IDX_UNIQUE_OPTION ON extensible_enum_option (deleted, extensible_enum_id, code)");

        $this->exec("ALTER TABLE measure DROP code");
        $this->getPDO()->exec("ALTER TABLE measure ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_8007192577153098EB3B4E33 ON measure (code, deleted)");
        $this->exec("DROP INDEX code ON measure");

        $this->exec("ALTER TABLE unit DROP code");
        $this->getPDO()->exec("ALTER TABLE unit ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_DCBB0C5377153098EB3B4E33 ON unit (code, deleted)");
        $this->exec("DROP INDEX code ON unit");

        $this->exec("DROP TABLE locale_measure");
        $this->exec("ALTER TABLE measure DROP data");

        $path = 'custom/Espo/Custom/Resources/metadata/entityDefs';
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                $filePath = "$path/$file";
                if (!is_file($filePath)) {
                    continue;
                }

                $contents = file_get_contents($filePath);

                if (strpos($contents, '"unit"') !== false) {
                    $data = json_decode($contents, true);
                    if (empty($data['fields'])) {
                        continue;
                    }
                    foreach ($data['fields'] as $field => $fieldDefs) {
                        if ($fieldDefs['type'] !== 'unit') {
                            continue;
                        }

                        $entityType = str_replace('.json', '', $file);
                        $tableName = Util::toUnderScore($entityType);
                        $columnName = Util::toUnderScore($field);

                        $this->exec("ALTER TABLE `$tableName` ADD {$columnName}_unit_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
                        foreach ($this->getMeasureUnits((string)$fieldDefs['measure']) as $unit) {
                            $name = $this->getPDO()->quote($unit['name']);
                            $this->exec("UPDATE `$tableName` SET {$columnName}_unit_id='{$unit['id']}' WHERE {$columnName}_unit={$name}");
                        }
                    }
                    $contents = str_replace(['"unit"', '"measure"'], ['"float"', '"measureId"'], $contents);
                    file_put_contents($filePath, $contents);
                }
            }
        }

        $this->getConfig()->set('mainLanguage', 'en_US');
        $this->getConfig()->save();

        $this->updateComposer('atrocore/core', '^1.6.0');
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    protected function getMeasureUnits(string $measureId): array
    {
        if (!isset($this->measureUnits[$measureId])) {
            $this->measureUnits[$measureId] = $this->getPDO()
                ->query("SELECT * FROM unit WHERE deleted=0 AND measure_id='$measureId'")
                ->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $this->measureUnits[$measureId];
    }
}
