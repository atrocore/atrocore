<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Services;

use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class UserFollowedRecord extends Base
{
    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $items = [];
        foreach ($collection as $entity) {
            $items[$entity->get('entityType')][] = $entity->get('entityId');
        }

        foreach ($items as $entityName => $ids) {
            $items[$entityName] = $this->getEntityManager()->getRepository($entityName)->where(['id' => $ids])->find();
        }

        foreach ($collection as $entity) {
            $entity->_entityNamePrepared = true;
            foreach ($items[$entity->get('entityType')] ?? [] as $item) {
                if ($entity->get('entityId') === $item->get('id')) {
                    $entity->set('entityName', $item->get('name'));
                    break;
                }
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_entityNamePrepared)) {
            $item = $this->getEntityManager()->getEntity($entity->get('entityType'), $entity->get('entityId'));
            if (!empty($item)) {
                $entity->set('entityName', $item->get('name'));
            }
        }
    }
}
