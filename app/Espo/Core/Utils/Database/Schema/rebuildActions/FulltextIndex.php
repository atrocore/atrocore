<?php

namespace Espo\Core\Utils\Database\Schema\rebuildActions;

class FulltextIndex extends \Espo\Core\Utils\Database\Schema\BaseRebuildActions
{
    public function beforeRebuild()
    {
        $currentSchema = $this->getCurrentSchema();
        $tables = $currentSchema->getTables();

        if (empty($tables)) return;

        $databaseHelper = new \Espo\Core\Utils\Database\Helper($this->getConfig());
        $connection = $databaseHelper->getDbalConnection();

        $metadataSchema = $this->getMetadataSchema();
        $tables = $metadataSchema->getTables();

        foreach ($tables as $table) {
            $tableName = $table->getName();
            $indexes = $table->getIndexes();

            foreach ($indexes as $index) {
                if (!$index->hasFlag('fulltext')) {
                    continue;
                }

                $columns = $index->getColumns();
                foreach ($columns as $columnName) {

                    $query = "SHOW FULL COLUMNS FROM `". $tableName ."` WHERE Field = '" . $columnName . "'";

                    try {
                        $row = $connection->fetchAssoc($query);
                    } catch (\Exception $e) {
                        continue;
                    }

                    switch (strtoupper($row['Type'])) {
                        case 'LONGTEXT':
                            $alterQuery = "ALTER TABLE `". $tableName ."` MODIFY `". $columnName ."` MEDIUMTEXT COLLATE ". $row['Collation'] ."";
                            $GLOBALS['log']->info('SCHEMA, Execute Query: ' . $alterQuery);
                            $connection->executeQuery($alterQuery);
                            break;
                    }
                }
            }
        }

    }
}

