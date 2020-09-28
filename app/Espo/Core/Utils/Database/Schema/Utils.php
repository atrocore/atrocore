<?php

namespace Espo\Core\Utils\Database\Schema;

use Espo\Core\Utils\Util;

class Utils
{
    public static function getIndexList(array $ormMeta, array $ignoreFlags = [])
    {
        $indexList = array();

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
                        $tableIndexName = static::generateIndexName($columnName);
                        $indexList[$entityName][$tableIndexName]['columns'] = array($columnName);
                    } else if (is_string($keyValue)) {
                        $tableIndexName = static::generateIndexName($keyValue);
                        $indexList[$entityName][$tableIndexName]['columns'][] = $columnName;
                    }
                }
            }

            if (isset($entityParams['indexes']) && is_array($entityParams['indexes'])) {
                foreach ($entityParams['indexes'] as $indexName => $indexParams) {
                    $tableIndexName = static::generateIndexName($indexName);

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

    public static function generateIndexName($name, $prefix = 'IDX', $maxLength = 30)
    {
        $nameList = [];
        $nameList[] = strtoupper($prefix);
        $nameList[] = strtoupper( Util::toUnderScore($name) );

        return substr(implode('_', $nameList), 0, $maxLength);
    }

    public static function getFieldListExceededIndexMaxLength(array $ormMeta, $indexMaxLength = 1000, array $indexList = null, $characterLength = 4)
    {
        $permittedFieldTypeList = [
            'varchar',
        ];

        $fields = array();

        if (!isset($indexList)) {
            $indexList = static::getIndexList($ormMeta, ['fulltext']);
        }

        foreach ($indexList as $entityName => $indexes) {
            foreach ($indexes as $indexName => $indexParams) {
                $columnList = $indexParams['columns'];

                $indexLength = 0;
                foreach ($columnList as $columnName) {
                    $fieldName = Util::toCamelCase($columnName);

                    if (!isset($ormMeta[$entityName]['fields'][$fieldName])) {
                        continue;
                    }

                    $indexLength += static::getFieldLength($ormMeta[$entityName]['fields'][$fieldName], $characterLength);
                }

                if ($indexLength > $indexMaxLength) {
                    foreach ($columnList as $columnName) {
                        $fieldName = Util::toCamelCase($columnName);
                        if (!isset($ormMeta[$entityName]['fields'][$fieldName])) {
                            continue;
                        }

                        $fieldType = static::getFieldType($ormMeta[$entityName]['fields'][$fieldName]);

                        if (in_array($fieldType, $permittedFieldTypeList)) {
                            if (!isset($fields[$entityName]) || !in_array($fieldName, $fields[$entityName])) {
                                $fields[$entityName][] = $fieldName;
                            }
                        }
                    }
                }
            }
        }

        return $fields;
    }

    protected static function getFieldLength(array $ormFieldDefs, $characterLength = 4)
    {
        $length = 0;

        if (isset($ormFieldDefs['notStorable']) && $ormFieldDefs['notStorable']) {
            return $length;
        }

        $defaultLength = array(
            'datetime' => 8,
            'time' => 4,
            'int' => 4,
            'bool' => 1,
            'float' => 4,
            'varchar' => 255,
        );

        $type = static::getFieldType($ormFieldDefs);

        $length = isset($defaultLength[$type]) ? $defaultLength[$type] : $length;
        $length = isset($ormFieldDefs['len']) ? $ormFieldDefs['len'] : $length;

        switch ($type) {
            case 'varchar':
                $length = $length * $characterLength;
                break;
        }

        return $length;
    }

    protected static function getFieldType(array $ormFieldDefs)
    {
        return isset($ormFieldDefs['dbType']) ? $ormFieldDefs['dbType'] : $ormFieldDefs['type'];
    }
}
