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

declare(strict_types=1);

namespace Atro\Core\Templates\Services;

use Atro\Core\Exceptions\Forbidden;
use Atro\Services\Record;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Archive extends Record
{
    public function createEntity($attachment)
    {
        throw new Forbidden();
    }

    public function follow($id, $userId = null)
    {
        throw new Forbidden();
    }

    public function unfollow($id, $userId = null)
    {
        throw new Forbidden();
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        if ($this->hasClickHouseIntegration()) {
            $links = $this->getMetadata()->get("entityDefs.{$collection->getEntityName()}.links") ?? [];
            foreach ($links as $link => $defs) {
                if (!empty($defs['type']) && !empty($defs['entity']) && $defs['type'] === 'belongsTo') {
                    $ids = array_values(array_unique(array_column($collection->toArray(), $link.'Id')));
                    if (!empty($ids)) {
                        $foreign = $this->getEntityManager()->getRepository($defs['entity'])
                            ->where(['id' => $ids])
                            ->find();
                        foreach ($collection as $entity) {
                            if (!empty($entity->get($link.'Id'))) {
                                foreach ($foreign as $foreignEntity) {
                                    if ($entity->get($link.'Id') === $foreignEntity->get('id')) {
                                        $entity->set($link.'Name', $foreignEntity->get('name'));
                                        $entity->_collectionPrepared = true;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if ($this->hasClickHouseIntegration() && empty($entity->_collectionPrepared)) {
            foreach ($this->getMetadata()->get("entityDefs.{$entity->getEntityName()}.links") ?? [] as $field => $defs) {
                if (!empty($defs['type']) && !empty($defs['entity']) && $defs['type'] === 'belongsTo' && !empty($entity->get($field.'Id'))) {
                    $foreign = $this->getEntityManager()->getRepository($defs['entity'])->get($entity->get($field.'Id'));
                    if (!empty($foreign)) {
                        $entity->set($field.'Name', $foreign->get('name'));
                    }
                }
            }
        }
    }

    protected function hasClickHouseIntegration(): bool
    {
        return class_exists('\ClickHouseIntegration\ORM\DB\ClickHouse\Query\QueryConverter') && !empty($this->getConfig()->get('clickhouse')['active']);
    }
}
