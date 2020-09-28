<?php

namespace Espo\Core\Utils\Database\Orm\Relations;

class HasChildren extends Base
{
    protected function load($linkName, $entityName)
    {
        $foreignLinkName = $this->getForeignLinkName();
        $foreignEntityName = $this->getForeignEntityName();

        return array(
            $entityName => array (
                'fields' => array(
                       $linkName.'Ids' => array(
                        'type' => 'varchar',
                        'notStorable' => true,
                    ),
                    $linkName.'Names' => array(
                        'type' => 'jsonObject',
                        'notStorable' => true,
                    ),
                ),
                'relations' => array(
                    $linkName => array(
                        'type' => 'hasChildren',
                        'entity' => $foreignEntityName,
                        'foreignKey' => $foreignLinkName.'Id',
                        'foreignType' => $foreignLinkName.'Type',
                        'foreign' => $foreignLinkName
                    ),
                ),
            ),
        );
    }


}