<?php

namespace Espo\Core\Utils\Database\Orm\Relations;

class HasOne extends Base
{
    protected function load($linkName, $entityName)
    {
        $linkParams = $this->getLinkParams();
        $foreignLinkName = $this->getForeignLinkName();
        $foreignEntityName = $this->getForeignEntityName();

        $relation = array(
            $entityName => array (
                'fields' => array(
                       $linkName.'Id' => array(
                        'type' => 'varchar',
                        'notStorable' => true
                    ),
                    $linkName.'Name' => array(
                        'type' => 'varchar',
                        'notStorable' => true
                    )
                ),
                'relations' => array(
                    $linkName => array(
                        'type' => 'hasOne',
                        'entity' => $foreignEntityName,
                        'foreignKey' => lcfirst($foreignLinkName.'Id'),
                        'foreign' => $foreignLinkName
                    )
                )
            )
        );

        return $relation;
    }
}