<?php

namespace Atro\Services;

use Atro\Core\Templates\Services\ReferenceData;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class SystemIcon extends ReferenceData
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $metadata = $this->getMetadata()->get(['app', 'systemIcons', $entity->get('id')], []);

        if (!empty($metadata) && !empty($metadata['path'])) {
            $entity->set('imageId', Util::generateUniqueHash());
            $entity->set('imageName', $entity->get('code') . '.svg');

            $entity->set('imagePathsData', [
                'download' => $metadata['path'],
                'thumbnails' => [
                    'small'  => $metadata['path'],
                    'medium' => $metadata['path'],
                    'large'  => $metadata['path']
                ]
            ]);
        }
    }

    public function putAclMeta(Entity $entity): void
    {
        parent::putAclMeta($entity);

        $entity->setMetaPermission('edit', false);
        $entity->setMetaPermission('delete', false);
    }
}
