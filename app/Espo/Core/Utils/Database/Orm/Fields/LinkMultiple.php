<?php

namespace Espo\Core\Utils\Database\Orm\Fields;

class LinkMultiple extends Base
{
    protected function load($fieldName, $entityName)
    {
        $data = [
            $entityName => [
                'fields' => [
                    $fieldName.'Ids' => [
                        'type' => 'jsonArray',
                        'notStorable' => true,
                        'isLinkMultipleIdList' => true,
                        'relation' => $fieldName,
                        'isUnordered' => true
                    ],
                    $fieldName.'Names' => [
                        'type' => 'jsonObject',
                        'notStorable' => true,
                        'isLinkMultipleNameMap' => true
                    ]
                ]
            ],
            'unset' => [
                $entityName => [
                    'fields.' . $fieldName
                ]
            ]
        ];

        $fieldParams = $this->getFieldParams();

        if (array_key_exists('orderBy', $fieldParams)) {
            $data[$entityName]['fields'][$fieldName . 'Ids']['orderBy'] = $fieldParams['orderBy'];
            if (array_key_exists('orderDirection', $fieldParams)) {
                $data[$entityName]['fields'][$fieldName . 'Ids']['orderDirection'] = $fieldParams['orderDirection'];
            }
        }

        $columns = $this->getMetadata()->get("entityDefs.{$entityName}.fields.{$fieldName}.columns");
        if (!empty($columns)) {
            $data[$entityName]['fields'][$fieldName . 'Columns'] = [
                'type' => 'jsonObject',
                'notStorable' => true,
            ];
        }

        return $data;
    }
}
