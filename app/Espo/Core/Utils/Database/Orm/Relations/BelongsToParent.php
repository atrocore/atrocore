<?php

namespace Espo\Core\Utils\Database\Orm\Relations;

class BelongsToParent extends Base
{
    protected function load($linkName, $entityName)
    {
        $linkParams = $this->getLinkParams();

        return array(
            $entityName => array (
                'fields' => array(
                    $linkName.'Id' => array(
                        'type' => 'foreignId',
                        'index' => $linkName,
                    ),
                    $linkName.'Type' => array(
                        'type' => 'foreignType',
                        'notNull' => false,
                        'index' => $linkName,
                        'len' => 100
                    ),
                    $linkName.'Name' => array(
                        'type' => 'varchar',
                        'notStorable' => true,
                    ),
                ),
                'relations' => array(
                    $linkName => array(
                        'type' => 'belongsToParent',
                        'key' => $linkName.'Id',
                    ),
                ),
            ),
        );

    }

}