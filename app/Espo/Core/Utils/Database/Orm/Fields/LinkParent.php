<?php

namespace Espo\Core\Utils\Database\Orm\Fields;

class LinkParent extends Base
{
    protected function load($fieldName, $entityName)
    {
        $data = array(
            $entityName => array (
                'fields' => array(
                    $fieldName.'Id' => array(
                        'type' => 'foreignId',
                        'index' => $fieldName
                    ),
                    $fieldName.'Type' => array(
                        'type' => 'foreignType',
                        'notNull' => false,
                        'index' => $fieldName,
                        'len' => 100
                    ),
                    $fieldName.'Name' => array(
                        'type' => 'varchar',
                        'notStorable' => true,
                        'relation' => $fieldName,
                        'isParentName' => true
                    )
                )
            ),
            'unset' => array(
                $entityName => array(
                    'fields.'.$fieldName
                )
            )
        );

        $fieldParams = $this->getFieldParams();

        if (!empty($fieldParams['defaultAttributes']) && array_key_exists($fieldName.'Id', $fieldParams['defaultAttributes'])) {
            $data[$entityName]['fields'][$fieldName.'Id']['default'] = $fieldParams['defaultAttributes'][$fieldName.'Id'];
        }
        if (!empty($fieldParams['defaultAttributes']) && array_key_exists($fieldName.'Type', $fieldParams['defaultAttributes'])) {
            $data[$entityName]['fields'][$fieldName.'Type']['default'] = $fieldParams['defaultAttributes'][$fieldName.'Type'];
        }

        return $data;
    }
}