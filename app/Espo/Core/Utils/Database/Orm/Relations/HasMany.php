<?php

namespace Espo\Core\Utils\Database\Orm\Relations;

class HasMany extends Base
{
    protected function load($linkName, $entityName)
    {
        $linkParams = $this->getLinkParams();
        $foreignLinkName = $this->getForeignLinkName();
        $foreignEntityName = $this->getForeignEntityName();

        $relationType = isset($linkParams['relationName']) ? 'manyMany' : 'hasMany';

        $relation = array(
            $entityName => array (
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
                        'type' => $relationType,
                        'entity' => $foreignEntityName,
                        'foreignKey' => lcfirst($foreignLinkName.'Id'),
                        'foreign' => $foreignLinkName
                    ),
                ),
            ),
        );

        return $relation;
    }


}