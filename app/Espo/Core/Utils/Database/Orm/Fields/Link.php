<?php

namespace Espo\Core\Utils\Database\Orm\Fields;

class Link extends Base
{
    protected function load($fieldName, $entityName)
    {
        $fieldParams = $this->getFieldParams();

        $data = array(
            $entityName => array (
                'fields' => array(
                    $fieldName.'Id' => array(
                        'type' => 'foreignId',
                        'index' => $fieldName
                    ),
                    $fieldName.'Name' => array(
                        'type' => 'varchar',
                        'notStorable' => true
                    )
                )
            ),
            'unset' => array(
                $entityName => array(
                    'fields.'.$fieldName
                )
            )
        );

        // prepare required
        if (!empty($fieldParams['required'])) {
            $data[$entityName]['fields'][$fieldName . 'Id']['required'] = true;
        }

        if (!empty($fieldParams['notStorable'])) {
            $data[$entityName]['fields'][$fieldName.'Id']['notStorable'] = true;
        }

        if (!empty($fieldParams['defaultAttributes']) && array_key_exists($fieldName.'Id', $fieldParams['defaultAttributes'])) {
            $data[$entityName]['fields'][$fieldName.'Id']['default'] = $fieldParams['defaultAttributes'][$fieldName.'Id'];
        }

        return $data;
    }
}