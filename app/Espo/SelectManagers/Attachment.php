<?php

namespace Espo\SelectManagers;

class Attachment extends \Espo\Core\SelectManagers\Base
{
    protected function filterOrphan(&$result)
    {
        $result['whereClause'][] = [
            'role' => ['Attachment', 'Inline Attachment'],
            'OR' => [
                [
                    'parentId' => null,
                    'parentType!=' => null,
                    'relatedType=' => null
                ],
                [
                    'parentType' => null,
                    'relatedId' => null,
                    'relatedType!=' => null
                ]
            ],
            'attachmentChild.id' => null
        ];

        $this->addLeftJoin(['Attachment', 'attachmentChild', [
            'attachmentChild.sourceId:' => 'attachment.id',
            'attachmentChild.deleted' => false
        ]], $result);

        $this->setDistinct(true, $result);
    }
}
