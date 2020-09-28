<?php

namespace Espo\Core\Utils\Database\Orm\Relations;

class EntityUser extends Base
{
    protected function load($linkName, $entityType)
    {
        $linkParams = $this->getLinkParams();
        $foreignEntityName = $this->getForeignEntityName();

        return array(
            $entityType => array(
                'relations' => array(
                    $linkName => array(
                        'type' => 'manyMany',
                        'entity' => $foreignEntityName,
                        'relationName' => lcfirst($linkParams['relationName']),
                        'midKeys' => array(
                            'entityId',
                            'userId'
                        ),
                        'conditions' => array(
                            'entityType' => $entityType
                        ),
                        'additionalColumns' => array(
                            'entityType' => array(
                                'type' => 'varchar',
                                'len' => 100
                            )
                        )
                    )
                )
            )
        );
    }

}