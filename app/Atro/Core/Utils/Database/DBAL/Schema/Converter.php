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

namespace Atro\Core\Utils\Database\DBAL\Schema;

use Atro\Core\Container;
use Atro\Core\Utils\Database\DBAL\Schema\FieldTypes\TypeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Espo\Core\Utils\Database\Schema\Utils as SchemaUtils;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Metadata\OrmMetadata;
use Espo\Core\Utils\Util;

class Converter
{
    protected Container $container;
    protected OrmMetadata $ormMetadata;
    protected Metadata $metadata;
    protected Connection $connection;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->ormMetadata = $container->get('ormMetadata');
        $this->metadata = $container->get('metadata');
        $this->connection = $container->get('connection');
    }

    public function createSchema(): Schema
    {
        $ormMetadata = array_merge($this->ormMetadata->getData(), $this->getSystemOrmMetadata());

        $indexList = SchemaUtils::getIndexList($ormMetadata);

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
            $uniqueFields = [];

            foreach ($entityDefs['fields'] as $fieldName => $fieldDefs) {
                if (!empty($fieldDefs['notStorable']) || empty($fieldDefs['type']) || $fieldDefs['type'] === 'foreign') {
                    continue;
                }

                if ($fieldDefs['type'] === 'id') {
                    $primaryColumns[] = Util::toUnderScore($fieldName);
                }

                $column = $this->addColumn($schema, $table, $fieldName, $fieldDefs);

                if (!empty($fieldDefs['unique']) && $fieldDefs['type'] !== 'id') {
                    $columnNames = [$column->getColumnName()];
                    if (isset($entityDefs['fields']['deleted'])) {
                        $columnNames[] = 'deleted';
                    }
                    $uniqueFields[] = $columnNames;
                }
            }

            foreach ($uniqueFields as $columnNames) {
                $table->addUniqueIndex($columnNames);
            }

            foreach ($this->metadata->get(['entityDefs', $entityName, 'uniqueIndexes'], []) as $indexName => $indexColumns) {
                $table->addUniqueIndex($indexColumns, SchemaUtils::generateIndexName($indexName, "IDX_{$tableName}", 120));
            }

            if (!empty($indexList[$entityName])) {
                foreach ($indexList[$entityName] as $indexName => $indexParams) {
                    $indexColumnList = $indexParams['columns'];
                    $indexFlagList = $indexParams['flags'] ?? [];

                    $options = [];

                    if (!SchemaUtils::isPgSQL($this->connection)) {
                        foreach ($indexParams['columns'] as $column) {
                            $type = $this->metadata->get(['entityDefs', $entityName, 'fields', Util::toCamelCase($column), 'type'], 'varchar');
                            if (in_array($type, ['text', 'wysiwyg'])) {
                                $options['lengths'] = [200];
                                break;
                            }
                        }
                    }

                    $table->addIndex($indexColumnList, $indexName, $indexFlagList, $options);
                }
            }

            $table->setPrimaryKey($primaryColumns);

            $tables[$entityName] = $table;
        }

        /**
         * ManyToMany
         */
        foreach ($ormMetadata as $entityDefs) {
            if (!isset($entityDefs['relations'])) {
                continue;
            }

            foreach ($entityDefs['relations'] as $relationParams) {
                if (empty($relationParams['type']) || $relationParams['type'] !== 'manyMany') {
                    continue;
                }

                $entityName = $relationParams['relationName'];

                if (!isset($tables[$entityName])) {
                    $tableName = Util::toUnderScore($entityName);

                    if ($schema->hasTable($tableName)) {
                        $table = $schema->getTable($tableName);
                    } else {
                        $table = $schema->createTable($tableName);

                        $uniqueIndex = [];

                        // ID column
                        $this->addColumn($schema, $table, 'id', ['type' => 'id', 'dbType' => 'int', 'autoincrement' => true]);

                        // DELETED column
                        $this->addColumn($schema, $table, 'deleted', ['type' => 'bool', 'default' => false]);

                        // MIDDLE columns
                        if (!empty($relationParams['midKeys'])) {
                            foreach ($relationParams['midKeys'] as $midKey) {
                                $column = $this->addColumn($schema, $table, $midKey, ['foreignId' => 'id', 'dbType' => 'varchar', 'len' => 24]);
                                $table->addIndex([$column->getColumnName()]);
                                $uniqueIndex[] = $column->getColumnName();
                            }
                        }

                        // ADDITIONAL columns
                        if (!empty($relationParams['additionalColumns'])) {
                            foreach ($relationParams['additionalColumns'] as $fieldName => $fieldParams) {
                                if (!isset($fieldParams['type'])) {
                                    $fieldParams = array_merge($fieldParams, array(
                                        'type' => 'varchar',
                                        'len'  => 255,
                                    ));
                                }

                                $this->addColumn($schema, $table, $fieldName, $fieldParams);
                            }
                        }

                        if (!empty($relationParams['conditions'])) {
                            foreach ($relationParams['conditions'] as $fieldName => $fieldParams) {
                                $uniqueIndex[] = Util::toUnderScore($fieldName);
                            }
                        }

                        if (!empty($uniqueIndex)) {
                            $table->addUniqueIndex($uniqueIndex);
                        }
                        $table->setPrimaryKey(['id']);
                    }

                    $tables[$entityName] = $table;
                }
            }
        }

        return $schema;
    }

    public function createColumn(string $fieldName, array $fieldDefs): TypeInterface
    {
        $className = $this->getColumnClassName($fieldDefs['dbType'] ?? $fieldDefs['type']);

        return new $className($fieldName, $fieldDefs, $this->connection);
    }

    public function addColumn(Schema $schema, Table $table, string $fieldName, array $fieldDefs): TypeInterface
    {
        $column = $this->createColumn($fieldName, $fieldDefs);

        if (!$table->hasColumn($column->getColumnName())) {
            $column->add($table, $schema);
        }

        return $column;
    }

    public function getColumnClassName(string $fieldType): string
    {
        return "\\Atro\\Core\\Utils\\Database\\DBAL\\Schema\\FieldTypes\\" . ucfirst($fieldType) . "Type";
    }

    protected function getSystemOrmMetadata(): array
    {
        return [
            'Autofollow'   => [
                'fields' => [
                    'id'         => [
                        'type'          => 'id',
                        'dbType'        => 'int',
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