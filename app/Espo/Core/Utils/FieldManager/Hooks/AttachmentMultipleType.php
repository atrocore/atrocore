<?php

namespace Espo\Core\Utils\FieldManager\Hooks;

use Espo\Core\Exceptions\Conflict;

class AttachmentMultipleType extends Base
{
    public function beforeSave($scope, $name, $defs, $options)
    {
        if (!empty($options['isNew'])) {
            $fieldDefs = $this->getMetadata()->get(['entityDefs', $scope, 'fields'], array());
            foreach ($fieldDefs as $field => $defs) {
                $type = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $field, 'type']);
                if ($type === 'attachmentMultiple') {
                    throw new Conflict("Attachment-Multiple field already exists in '{$scope}'. There can be only one Attachment-Multiple field per entity type.");
                }
            }
        }
    }
}