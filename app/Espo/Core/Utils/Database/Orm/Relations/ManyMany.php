<?php

namespace Espo\Core\Utils\Database\Orm\Relations;

use Espo\Core\Utils\Util;

class ManyMany extends Base
{
    protected function load($linkName, $entityName)
    {
        $foreignEntityName = $this->getForeignEntityName();
        $foreignLinkName = $this->getForeignLinkName();

        $linkParams = $this->getLinkParams();

        if (!empty($linkParams['relationName'])) {
            $relationName = $linkParams['relationName'];
        } else {
            $relationName = $this->getJoinTable($entityName, $foreignEntityName);
        }

        return array(
            $entityName => array(
                'fields' => array(
                       $linkName.'Ids' => array(
                        'type' => 'jsonArray',
                        'notStorable' => true,
                    ),
                    $linkName.'Names' => array(
                        'type' => 'jsonObject',
                        'notStorable' => true,
                    ),
                ),
                'relations' => array(
                    $linkName => array(
                        'type' => 'manyMany',
                        'entity' => $foreignEntityName,
                        'relationName' => $relationName,
                        'key' => 'id', //todo specify 'key'
                        'foreignKey' => 'id', //todo specify 'foreignKey'
                        'midKeys' => array(
                            lcfirst($entityName).'Id',
                            lcfirst($foreignEntityName).'Id',
                        ),
                        'foreign' => $foreignLinkName
                    ),
                ),
            ),
        );
    }

    protected function getJoinTable($tableName1, $tableName2)
    {
        $tables = $this->getSortEntities($tableName1, $tableName2);

        return Util::toCamelCase( implode('_', $tables) );
    }

    protected function getSortEntities($entity1, $entity2)
    {
        $entities = array(
            Util::toCamelCase(lcfirst($entity1)),
            Util::toCamelCase(lcfirst($entity2)),
        );

        sort($entities);

        return $entities;
    }

}