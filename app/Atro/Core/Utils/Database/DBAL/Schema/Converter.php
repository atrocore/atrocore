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

namespace Atro\Core\Utils\Database\DBAL\Schema;

use Atro\Core\Container;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
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

    public static function getColumnName(string $fieldName): string
    {
        return Util::toUnderScore($fieldName);
    }

    public static function generateIndexName(string $entityName, string $indexName): string
    {
        $res = Util::toUnderScore($entityName) . '_' . Util::toUnderScore($indexName);
        if (strlen($res) > 55) {
            $res = md5($res);
        }

        return 'IDX_' . strtoupper($res);
    }

    public static function isPgSQL(Connection $connection): bool
    {
        return strpos(get_class($connection->getDriver()), 'PgSQL') !== false;
    }

    public function createSchema(): Schema
    {
        $ormMetadata = array_merge($this->ormMetadata->getData(), $this->getSystemOrmMetadata());

        $indexList = $this->getIndexList($ormMetadata);

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

                $this->addColumn($schema, $table, $fieldName, $fieldDefs);

                if (
                    !empty($fieldDefs['unique'])
                    && !in_array($fieldDefs['type'], ['id', 'autoincrement'])
                    && empty($fieldDefs['autoincrement'])
                ) {
                    $columnNames = [self::getColumnName($fieldName)];
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
                $table->addUniqueIndex($indexColumns, self::generateIndexName($entityName, $indexName));
            }

            if (!empty($indexList[$entityName])) {
                foreach ($indexList[$entityName] as $indexName => $indexParams) {
                    $indexColumnList = $indexParams['columns'];
                    $indexFlagList = $indexParams['flags'] ?? [];

                    $options = [];

                    if (!self::isPgSQL($this->connection)) {
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

        return $schema;
    }

    public function addColumn(Schema $schema, Table $table, string $fieldName, array $fieldDefs): void
    {
        $columnName = self::getColumnName($fieldName);

        $fieldDefs['notnull'] = !empty($fieldDefs['notNull']);
        if (isset($fieldDefs['len'])) {
            $fieldDefs['length'] = $fieldDefs['len'];
        }

        $type = $fieldDefs['dbType'] ?? $fieldDefs['type'];

        $allowedParams = ['notnull', 'comment', 'default'];

        switch ($type) {
            case 'bool':
                $type = 'boolean';
                break;
            case 'jsonArray':
                $allowedParams = ['notnull', 'comment'];
                break;
            case 'jsonObject':
                $allowedParams = ['notnull', 'comment'];
                break;
            case 'varchar':
                $type = 'string';
                $allowedParams[] = 'length';
                break;
            case 'text':
                $allowedParams = ['notnull', 'comment', 'length'];
                if (!empty($fieldDefs['default'])) {
                    $fieldDefs['comment'] = "default={" . $fieldDefs['default'] . "}";
                }
                break;
            case 'int':
                $type = 'integer';
                $allowedParams[] = 'autoincrement';
                if (!empty($fieldDefs['autoincrement'])) {
                    if (self::isPgSQL($this->connection)) {
                        $sequence = "{$table->getName()}_{$columnName}_seq";
                        $schema->createSequence($sequence);
                        $fieldDefs['default'] = "nextval('$sequence')";
                        unset($fieldDefs['autoincrement']);
                    }
                    $fieldDefs['notnull'] = true;
                }
                break;
        }

        foreach ($fieldDefs as $key => $value) {
            if (!in_array($key, $allowedParams)) {
                unset($fieldDefs[$key]);
            }
        }

        if (!$table->hasColumn($columnName)) {
            $table->addColumn($columnName, $type, $fieldDefs);
        }

        if (!empty($fieldDefs['autoincrement'])) {
            $table->addUniqueIndex([$columnName]);
        }
    }

    protected function getIndexList(array $ormMeta, array $ignoreFlags = []): array
    {
        $indexList = [];

        foreach ($ormMeta as $entityName => $entityParams) {
            foreach ($entityParams['fields'] as $fieldName => $fieldParams) {
                if (isset($fieldParams['notStorable']) && $fieldParams['notStorable']) {
                    continue;
                }

                if (isset($fieldParams['index'])) {
                    $keyValue = $fieldParams['index'];
                    $columnName = Util::toUnderScore($fieldName);

                    if (!isset($indexList[$entityName])) {
                        $indexList[$entityName] = [];
                    }

                    if ($keyValue === true) {
                        $tableIndexName = self::generateIndexName($entityName, $columnName);
                        $indexList[$entityName][$tableIndexName]['columns'] = array($columnName);
                        if (array_key_exists('deleted', $entityParams['fields'])) {
                            $indexList[$entityName][$tableIndexName]['columns'] = [$columnName, 'deleted'];
                        }
                    } else {
                        if (is_string($keyValue)) {
                            $tableIndexName = self::generateIndexName($entityName, $keyValue);
                            $indexList[$entityName][$tableIndexName]['columns'][] = $columnName;
                        }
                    }
                }
            }

            if (isset($entityParams['indexes']) && is_array($entityParams['indexes'])) {
                foreach ($entityParams['indexes'] as $indexName => $indexParams) {
                    $tableIndexName = self::generateIndexName($entityName, $indexName);

                    if (isset($indexParams['flags']) && is_array($indexParams['flags'])) {

                        $skipIndex = false;
                        foreach ($ignoreFlags as $ignoreFlag) {
                            if (($flagKey = array_search($ignoreFlag, $indexParams['flags'])) !== false) {
                                unset($indexParams['flags'][$flagKey]);
                                $skipIndex = true;
                            }
                        }

                        if ($skipIndex && empty($indexParams['flags'])) {
                            continue;
                        }

                        $indexList[$entityName][$tableIndexName]['flags'] = $indexParams['flags'];
                    }

                    if (is_array($indexParams['columns'])) {
                        $indexList[$entityName][$tableIndexName]['columns'] = Util::toUnderScore($indexParams['columns']);
                    }
                }
            }
        }

        return $indexList;
    }

    protected function getSystemOrmMetadata(): array
    {
        return [
            'Preferences'        => [
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
            'UserFollowedRecord' => [
                'fields'        => [
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
                ]
            ],
        ];
    }
}