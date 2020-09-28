<?php

namespace Espo\Core\Utils\Database\DBAL\Schema;

class Schema extends \Doctrine\DBAL\Schema\Schema
{
    /**
     * Creates a new table.
     *
     * @param string $tableName
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function createTable($tableName)
    {
        $table = new Table($tableName);
        $this->_addTable($table);

        foreach ($this->_schemaConfig->getDefaultTableOptions() as $name => $value) {
            $table->addOption($name, $value);
        }

        return $table;
    }

}