<?php

namespace Espo\Core\Utils\Database\Orm\Fields;

class AttachmentMultiple extends Base
{
    protected function load($fieldName, $entityType)
    {
        $data = array(
            $entityType => array (
                'fields' => array(
                    $fieldName.'Ids' => array(
                        'type' => 'jsonArray',
                        'notStorable' => true,
                        'orderBy' => [['createdAt', 'ASC'], ['name', 'ASC']],
                        'isLinkMultipleIdList' => true,
                        'relation' => $fieldName
                    ),
                    $fieldName.'Names' => array(
                        'type' => 'jsonObject',
                        'notStorable' => true,
                        'isLinkMultipleNameMap' => true
                    )
                )
            ),
            'unset' => array(
                $entityType => array(
                    'fields.'.$fieldName,
                )
            )
        );

        return $data;
    }
}
