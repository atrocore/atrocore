<?php

namespace Espo\Core\Utils\Database\Orm\Fields;

class File extends Base
{
    protected function load($fieldName, $entityName)
    {
        $fieldParams = $this->getFieldParams();

        $data = array(
            $entityName => array (
                'fields' => array(
                    $fieldName.'Id' => array(
                        'type' => 'foreignId',
                        'index' => false
                    ),
                    $fieldName.'Name' => array(
                        'type' => 'foreign'
                    )
                )
            ),
            'unset' => array(
                $entityName => array(
                    'fields.'.$fieldName
                )
            )
        );
        if (!empty($fieldParams['notStorable'])) {
            $data[$entityName]['fields'][$fieldName.'Id']['notStorable'] = true;
            $data[$entityName]['fields'][$fieldName.'Name']['type'] = 'varchar';
        }

        if (!empty($fieldParams['defaultAttributes']) && array_key_exists($fieldName.'Id', $fieldParams['defaultAttributes'])) {
            $data[$entityName]['fields'][$fieldName.'Id']['default'] = $fieldParams['defaultAttributes'][$fieldName.'Id'];
        }

        if (empty($fieldParams['notStorable'])) {
            $data[$entityName]['fields'][$fieldName . 'Name']['relation'] = $fieldName;
            $data[$entityName]['fields'][$fieldName . 'Name']['foreign'] = 'name';

            $linkName = $fieldName;
            $data[$entityName]['relations'] = array();
            $data[$entityName]['relations'][$linkName] = array(
                'type' => 'belongsTo',
                'entity' => 'Attachment',
                'key' => $linkName.'Id',
                'foreignKey' => 'id',
                'foreign' => null
            );
        }

        return $data;
    }
}