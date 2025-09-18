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
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;

class V2Dot1Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-17 10:00:00');
    }

    public function up(): void
    {
        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');
        $scriptFields = [];
        $saveMetadata = [];
        foreach ($metadata->get('entityDefs') ?? [] as $scope => $entityDefs) {
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if(empty($fieldDefs['type']) || $fieldDefs['type'] !== 'script') {
                    continue;
                }

                $scriptFields[$scope][] = [
                    "name" => $field,
                    "outputType" => $fieldDefs['outputType'] ?? 'text',
                    "isMultilang" => $fieldDefs['isMultilang'] ?? false,
                ];

                if(!empty($entityDefs['notStorable'])) {
                    $metadata->set('entityDefs', $scope, [
                        'fields' => [
                            $field => [
                                'notStorable' => false,
                            ],
                        ],
                    ]);
                    $saveMetadata = true;
                }
            }
        }

        if($saveMetadata) {
            $metadata->save();
        }


        foreach ($scriptFields as $scope => $fields) {
            $fromSchema = $this->getCurrentSchema();
            $toSchema = clone $fromSchema;

            $tableName = Util::toUnderScore($scope);
            if ($toSchema->hasTable($tableName)) {
                $table = $toSchema->getTable($tableName);



                foreach ($fields as $field) {
                    $type = $this->getType($field['outputType']);

                    if (!$table->hasColumn(util::toUnderScore($field['name']))) {
                        $table->addColumn(Util::toUnderScore($field['name']), $type, ['notnull' => false]);
                    }

                    if (!empty($fieldDefs['isMultilang']) && !empty($this->getConfig()->get('isMultilangActive')) && in_array($type, ['text', 'wysiwyg'])) {
                        foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                            $nField = Util::toUnderScore($field['name']). '_'. strtolower($language);
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

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }

    public function getType($type) {
        if($type === 'int'){
            return 'integer';
        }

        if($type === 'bool'){
            return 'boolean';
        }

        if(in_array($type, ['text', 'date', 'datetime', 'float'])){
            return $type;
        }

        return 'text';
    }
}
