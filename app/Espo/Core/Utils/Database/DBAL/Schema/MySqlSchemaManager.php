<?php

namespace Espo\Core\Utils\Database\DBAL\Schema;

use Doctrine\DBAL\Events;
use Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs;

class MySqlSchemaManager extends \Doctrine\DBAL\Schema\MySqlSchemaManager
{
    public function createSchema()
    {
        $sequences = array();
        if ($this->_platform->supportsSequences()) {
            $sequences = $this->listSequences();
        }
        $tables = $this->listTables();

        return new Schema($tables, $sequences, $this->createSchemaConfig());
    }

    public function listTables()
    {
        $tableNames = $this->listTableNames();

        $tables = array();
        foreach ($tableNames as $tableName) {
            $tables[] = $this->listTableDetails($tableName);
        }

        return $tables;
    }

    public function listTableDetails($tableName)
    {
        $columns = $this->listTableColumns($tableName);
        $foreignKeys = array();
        if ($this->_platform->supportsForeignKeyConstraints()) {
            $foreignKeys = $this->listTableForeignKeys($tableName);
        }
        $indexes = $this->listTableIndexes($tableName);

        return new Table($tableName, $columns, $indexes, $foreignKeys, false, array());
    }

    public function listTableIndexes($table)
    {
        $sql = $this->_platform->getListTableIndexesSQL($table, $this->_conn->getDatabase());

        $tableIndexes = $this->_conn->fetchAll($sql);

        return $this->_getPortableTableIndexesList($tableIndexes, $table);
    }

    protected function _getPortableTableIndexesList($tableIndexes, $tableName=null)
    {
        foreach($tableIndexes as $k => $v) {
            $v = array_change_key_case($v, CASE_LOWER);
            if($v['key_name'] == 'PRIMARY') {
                $v['primary'] = true;
            } else {
                $v['primary'] = false;
            }
            if (strpos($v['index_type'], 'FULLTEXT') !== false) {
                $v['flags'] = array('FULLTEXT');
            }
            $tableIndexes[$k] = $v;
        }

        $result = array();
        foreach($tableIndexes as $tableIndex) {

            $indexName = $keyName = $tableIndex['key_name'];
            if ($tableIndex['primary']) {
                $keyName = 'primary';
            }
            $keyName = strtolower($keyName);

            if (!isset($result[$keyName])) {
                $result[$keyName] = array(
                    'name' => $indexName,
                    'columns' => array($tableIndex['column_name']),
                    'unique' => $tableIndex['non_unique'] ? false : true,
                    'primary' => $tableIndex['primary'],
                    'flags' => isset($tableIndex['flags']) ? $tableIndex['flags'] : array(),
                );
            } else {
                $result[$keyName]['columns'][] = $tableIndex['column_name'];
            }
        }

        $eventManager = $this->_platform->getEventManager();

        $indexes = array();
        foreach($result as $indexKey => $data) {
            $index = null;
            $defaultPrevented = false;

            if (null !== $eventManager && $eventManager->hasListeners(Events::onSchemaIndexDefinition)) {
                $eventArgs = new SchemaIndexDefinitionEventArgs($data, $tableName, $this->_conn);
                $eventManager->dispatchEvent(Events::onSchemaIndexDefinition, $eventArgs);

                $defaultPrevented = $eventArgs->isDefaultPrevented();
                $index = $eventArgs->getIndex();
            }

            if ( ! $defaultPrevented) {
                $index = new Index($data['name'], $data['columns'], $data['unique'], $data['primary'], $data['flags']);
            }

            if ($index) {
                $indexes[$indexKey] = $index;
            }
        }

        return $indexes;
    }
}