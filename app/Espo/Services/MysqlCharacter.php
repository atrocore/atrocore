<?php

namespace Espo\Services;

use Espo\Core\Utils\Database\Schema\Utils as SchemaUtils;

class MysqlCharacter extends \Espo\Core\Services\Base
{
    protected function init()
    {
        $this->addDependency('container');
    }

    protected function getContainer()
    {
        return $this->getInjection('container');
    }

    public function jobConvertToMb4()
    {
        $container = $this->getContainer();

        $pdo = $container->get('entityManager')->getPDO();
        $ormMeta = $container->get('ormMetadata')->getData(true);

        $databaseSchema = $container->get('schema');
        $maxIndexLength = $databaseSchema->getDatabaseHelper()->getMaxIndexLength();
        if ($maxIndexLength > 1000) {
            $maxIndexLength = 1000;
        }

        //Account name
        $sth = $pdo->prepare("SELECT `name` FROM `account` WHERE LENGTH(name) > 249");
        $sth->execute();
        $row = $sth->fetch(\PDO::FETCH_ASSOC);
        if (empty($row)) {
            $sth = $pdo->prepare("ALTER TABLE `account` MODIFY `name` VARCHAR(249)");
            $sth->execute();
        }

        $fieldListExceededIndexMaxLength = SchemaUtils::getFieldListExceededIndexMaxLength($ormMeta, $maxIndexLength);

        foreach ($ormMeta as $entityName => $entityParams) {

            $tableName = \Espo\Core\Utils\Util::toUnderScore($entityName);

            //Get table columns params
            $query = "SHOW FULL COLUMNS FROM `". $tableName ."` WHERE `Collation` <> 'utf8mb4_unicode_ci'";

            try {
                $sth = $pdo->prepare($query);
                $sth->execute();
            } catch (\Exception $e) {
                $GLOBALS['log']->debug('Utf8mb4: Table does not exist - ' . $e->getMessage());
                continue;
            }

            $columnParams = array();
            $rowList = $sth->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rowList as $row) {
                $columnParams[ $row['Field'] ] = $row;
            }
            //END: get table columns params

            foreach ($entityParams['fields'] as $fieldName => $fieldParams) {

                $columnName = \Espo\Core\Utils\Util::toUnderScore($fieldName);

                if (isset($fieldParams['notStorable']) && $fieldParams['notStorable']) {
                    continue;
                }

                if (isset($fieldListExceededIndexMaxLength[$entityName]) && in_array($fieldName, $fieldListExceededIndexMaxLength[$entityName])) {
                    continue;
                }

                if (!isset($columnParams[$columnName]) || empty($columnParams[$columnName]['Type'])) {
                    continue;
                }

                $query = null;

                switch ($fieldParams['type']) {
                    case 'varchar':
                    case 'text':
                    case 'jsonObject':
                    case 'jsonArray':
                        $query = "ALTER TABLE `".$tableName."`
                            CHANGE COLUMN `". $columnName ."` `". $columnName ."` ". $columnParams[$columnName]['Type'] ."
                            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                        ";
                        break;
                }

                if (!empty($query)) {
                    $GLOBALS['log']->debug('Utf8mb4: execute the query - [' . $query . '].');

                    try {
                        $sth = $pdo->prepare($query);
                        $sth->execute();
                    } catch (\Exception $e) {
                        $GLOBALS['log']->warning('Utf8mb4: FAILED executing the query - [' . $query . '], details: '. $e->getMessage() .'.');
                    }
                }
            }
        }

        $config = $container->get('config');
        $database = $config->get('database');
        if (!isset($database['charset']) || $database['charset'] != 'utf8mb4') {
            $database['charset'] = 'utf8mb4';
            $config->set('database', $database);
            $config->save();
        }

        $this->getContainer()->get('dataManager')->rebuild();
    }
}