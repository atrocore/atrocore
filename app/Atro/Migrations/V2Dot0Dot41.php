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
use Doctrine\DBAL\ParameterType;

class V2Dot0Dot41 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-09 15:00:00');
    }

    public function up(): void
    {
        $path = 'data/metadata/entityDefs';
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                $scope = explode('.', $file)[0];
                $entityDefs = @json_decode(file_get_contents("$path/$file"), true);

                if(empty($entityDefs['fields'])) {
                    continue;
                }

                $fromSchema = $this->getCurrentSchema();
                $toSchema = clone $fromSchema;
                foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                    if(empty($fieldDefs['type']) || $fieldDefs['type'] !== 'script') {
                        continue;
                    }

                    $tableName = Util::toUnderScore($scope);
                    if ($toSchema->hasTable($tableName)) {
                        $table = $toSchema->getTable($tableName);

                        $type = $this->getType($fieldDefs['outputType'] ?? 'text');

                        if (!$table->hasColumn(util::toUnderScore($field))) {
                            $table->addColumn(Util::toUnderScore($field), $type, ['notnull' => false]);
                        }

                        if (!empty($fieldDefs['isMultilang']) && !empty($this->getConfig()->get('isMultilangActive')) && in_array($type, ['text', 'wysiwyg'])) {
                            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                                $nField = Util::toUnderScore($field). '_'. strtolower($language);
                                if(!$table->hasColumn($nField)) {
                                    $table->addColumn($nField, $type, ['notnull' => false]);
                                }
                            }
                        }
                    }
                }

                foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                    $this->exec($sql);
                }
            }
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
//            var_dump($e);
        }
    }

    public function getType($type) {
        if($type === 'int'){
            return 'integer';
        }

        if(in_array($type, ['text', 'date', 'datetime', 'float'])){
            return $type;
        }

        return 'text';
    }
}
