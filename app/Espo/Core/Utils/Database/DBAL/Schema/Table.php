<?php

namespace Espo\Core\Utils\Database\DBAL\Schema;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\SchemaException;

class Table extends \Doctrine\DBAL\Schema\Table
{
    /**
     * @param string $columnName
     * @param string $typeName
     * @param array  $options
     *
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function addColumn($columnName, $typeName, array $options=array())
    {
        $column = new Column($columnName, Type::getType($typeName), $options);

        $this->_addColumn($column);

        return $column;
    }

    public function addIndex(array $columnNames, $indexName = null, array $flags = array())
    {
        if($indexName == null) {
            $indexName = $this->_generateIdentifierName(
                array_merge(array($this->getName()), $columnNames), "idx", $this->_getMaxIdentifierLength()
            );
        }

        return $this->_createIndex($columnNames, $indexName, false, false, $flags);
    }

    private function _createIndex(array $columnNames, $indexName, $isUnique, $isPrimary, array $flags = array())
    {
        if (preg_match('(([^a-zA-Z0-9_]+))', $indexName)) {
            throw SchemaException::indexNameInvalid($indexName);
        }

        foreach ($columnNames as $columnName => $indexColOptions) {
            if (is_numeric($columnName) && is_string($indexColOptions)) {
                $columnName = $indexColOptions;
            }

            if ( ! $this->hasColumn($columnName)) {
                throw SchemaException::columnDoesNotExist($columnName, $this->_name);
            }
        }

        $this->_addIndex(new Index($indexName, $columnNames, $isUnique, $isPrimary, $flags));

        return $this;
    }
}