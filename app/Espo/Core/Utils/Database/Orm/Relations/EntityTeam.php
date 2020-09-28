<?php

namespace Espo\Core\Utils\Database\Orm\Relations;

class EntityTeam extends Base
{
    protected function load($linkName, $entityName)
    {
        $linkParams = $this->getLinkParams();
        $foreignEntityName = $this->getForeignEntityName();

        return [
            $entityName => [
                'relations' => [
                    $linkName => [
                        'type' => 'manyMany',
                        'entity' => $foreignEntityName,
                        'relationName' => lcfirst($linkParams['relationName']),
                        'midKeys' => [
                            'entityId',
                            'teamId'
                        ],
                        'conditions' => [
                            'entityType' => $entityName
                        ],
                        'additionalColumns' => [
                            'entityType' => [
                                'type' => 'varchar',
                                'len' => 100
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

}