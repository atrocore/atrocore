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

namespace Atro\Core\Utils\Database\Schema;

use Atro\Core\Container;
use Atro\Core\Utils\Database\Schema\Columns\ColumnInterface;
use Doctrine\DBAL\Schema\Schema;
use Espo\Core\Utils\Database\Schema\Utils as SchemaUtils;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Metadata\OrmMetadata;
use Espo\Core\Utils\Util;

/**
 * @todo 1. delete app/Espo/Core/Utils/Database/Schema/tables/
 */
class Converter
{
    protected Container $container;
    protected OrmMetadata $ormMetadata;
    protected Metadata $metadata;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->ormMetadata = $container->get('ormMetadata');
        $this->metadata = $container->get('metadata');
    }

    public function createSchema(): Schema
    {
        $ormMetadata = Util::unsetInArray(array_merge($this->ormMetadata->getData(), $this->getSystemOrmMetadata()), ['Preferences', 'Settings']);

        $schema = new Schema();

        $tables = [];
        foreach ($ormMetadata as $entityName => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }

            $tableName = Util::toUnderScore($entityName);
            if ($schema->hasTable($tableName)) {
                $table = $schema->getTable($tableName);
            } else {
                $table = $schema->createTable($tableName);
            }

            $primaryColumns = [];

            foreach ($entityDefs['fields'] as $fieldName => $fieldDefs) {
                if (!empty($fieldDefs['notStorable']) || empty($fieldDefs['type']) || $fieldDefs['type'] === 'foreign') {
                    continue;
                }

                if ($fieldDefs['type'] === 'id') {
                    $primaryColumns[] = Util::toUnderScore($fieldName);
                }

                $fieldType = $fieldDefs['dbType'] ?? $fieldDefs['type'];

                $className = "\\Atro\\Core\\Utils\\Database\\Schema\\Columns\\" . ucfirst($fieldType) . "Column";

                $column = new $className($fieldName, $fieldDefs);
                if (!$column instanceof ColumnInterface) {
                    throw new \Error("No such column type '{$fieldDefs['type']}'.");
                }

                if (!$table->hasColumn($column->getColumnName())) {
                    $column->add($table);
                }

//                if (!empty($fieldDefs['unique']) && $fieldDefs['type'] !== 'id') {
//                    $columnNames = [];
//                    if (isset($ormMetadata[$entityName]['fields']['deleted'])) {
//                        $columnNames[] = 'deleted';
//                    }
//                    $columnNames[] = $column->getColumnName();
//
//                    $table->addUniqueIndex($columnNames);
//                }
            }

//            foreach ($this->metadata->get(['entityDefs', $entityName, 'uniqueIndexes'], []) as $indexName => $indexColumns) {
//                $table->addUniqueIndex($indexColumns, SchemaUtils::generateIndexName($indexName));
//            }

            $table->setPrimaryKey($primaryColumns);

            $tables[$entityName] = $table;
        }

//        echo '<pre>';
//        print_r('q11');
//        die();

        // $indexList = SchemaUtils::getIndexList($ormMeta);
        //        $fieldListExceededIndexMaxLength = SchemaUtils::getFieldListExceededIndexMaxLength($ormMeta, $this->getMaxIndexLength());
        //
        //        $tables = array();
        //        foreach ($ormMeta as $entityName => $entityParams) {
        //            if (!empty($indexList[$entityName])) {
        //                foreach($indexList[$entityName] as $indexName => $indexParams) {
        //                    $indexColumnList = $indexParams['columns'];
        //                    $indexFlagList = isset($indexParams['flags']) ? $indexParams['flags'] : array();
        //                    $tables[$entityName]->addIndex($indexColumnList, $indexName, $indexFlagList);
        //                }
        //            }
        //        }
        //
        //        //check and create columns/tables for relations
        //        foreach ($ormMeta as $entityName => $entityParams) {
        //
        //            if (!isset($entityParams['relations'])) {
        //                continue;
        //            }
        //
        //            foreach ($entityParams['relations'] as $relationName => $relationParams) {
        //
        //                 switch ($relationParams['type']) {
        //                    case 'manyMany':
        //                        $tableName = $relationParams['relationName'];
        //
        //                        //check for duplicate tables
        //                        if (!isset($tables[$tableName])) { //no needs to create the table if it already exists
        //                            $tables[$tableName] = $this->prepareManyMany($entityName, $relationParams, $tables);
        //                        }
        //                        break;
        //                }
        //            }
        //        }
        //        //END: check and create columns/tables for relations

        return $schema;
    }

    public function getDbFieldParams(array $fieldParams): array
    {
        $dbFieldParams = [];

        foreach (self::$allowedDbFieldParams as $espoName => $dbalName) {
            if (isset($fieldParams[$espoName])) {
                $dbFieldParams[$dbalName] = $fieldParams[$espoName];
            }
        }

        switch ($fieldParams['type']) {
            case 'array':
            case 'jsonArray':
            case 'text':
            case 'longtext':
                if (!empty($dbFieldParams['default'])) {
                    $dbFieldParams['comment'] = "default={" . $dbFieldParams['default'] . "}";
                }
                unset($dbFieldParams['default']); //for db type TEXT can't be defined a default value
                break;

            case 'bool':
                $default = false;
                if (array_key_exists('default', $dbFieldParams)) {
                    $default = $dbFieldParams['default'];
                }
                $dbFieldParams['default'] = intval($default);
                break;
        }

        if (isset($fieldParams['autoincrement']) && $fieldParams['autoincrement']) {
            $dbFieldParams['unique'] = true;
            $dbFieldParams['notnull'] = true;
        }

        if (isset($fieldParams['utf8mb3']) && $fieldParams['utf8mb3']) {
            $dbFieldParams['platformOptions'] = ['collation' => 'utf8_unicode_ci'];
        }

        return $dbFieldParams;
    }

    protected function getSystemOrmMetadata(): array
    {
        return [
            'Autofollow'   => [
                'fields' => [
                    'id'         => [
                        'type'          => 'id',
                        'dbType'        => 'int',
                        'len'           => '11',
                        'autoincrement' => true,
                        'unique'        => true,
                    ],
                    'entityType' => [
                        'type'  => 'varchar',
                        'len'   => '100',
                        'index' => 'entityType',
                    ],
                    'userId'     => [
                        'type'  => 'varchar',
                        'len'   => '24',
                        'index' => true,
                    ]
                ]
            ],
            'Preferences'  => [
                'fields' => [
                    'id'   => [
                        'dbType' => 'varchar',
                        'len'    => 24,
                        'type'   => 'id'
                    ],
                    'data' => [
                        'type' => 'text'
                    ]
                ]
            ],
            'Subscription' => [
                'fields' => [
                    'id'         => [
                        'type'          => 'id',
                        'dbType'        => 'int',
                        'len'           => '11',
                        'autoincrement' => true,
                        'unique'        => true,
                    ],
                    'entityId'   => [
                        'type'  => 'varchar',
                        'len'   => '24',
                        'index' => 'entity',
                    ],
                    'entityType' => [
                        'type'  => 'varchar',
                        'len'   => '100',
                        'index' => 'entity',
                    ],
                    'userId'     => [
                        'type'  => 'varchar',
                        'len'   => '24',
                        'index' => true,
                    ],
                ],
            ],
        ];
    }
}