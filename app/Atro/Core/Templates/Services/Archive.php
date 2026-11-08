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

use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Atro\Core\Services\AbstractRecordService;
use Atro\Core\Services\Interfaces\PersonalDataErasableInterface;
use Atro\Core\Services\Traits\PersonalDataErasableTrait;

/**
 * The service of the entities of type Archive: the records are only read.
 */
class Archive extends AbstractRecordService implements PersonalDataErasableInterface
{
    use PersonalDataErasableTrait;

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        if ($this->hasClickHouse()) {
            $links = $this->getMetadata()->get("entityDefs.{$collection->getEntityName()}.links") ?? [];
            foreach ($links as $link => $defs) {
                if (!empty($defs['type']) && !empty($defs['entity']) && $defs['type'] === 'belongsTo') {
                    $ids = array_values(array_unique(array_column($collection->toArray(), $link . 'Id')));
                    if (!empty($ids)) {
                        $foreign = $this->getEntityManager()->getRepository($defs['entity'])
                            ->where(['id' => $ids])
                            ->find();
                        foreach ($collection as $entity) {
                            if (!empty($entity->get($link . 'Id'))) {
                                foreach ($foreign as $foreignEntity) {
                                    if ($entity->get($link . 'Id') === $foreignEntity->get('id')) {
                                        $entity->set($link . 'Name', $foreignEntity->get('name'));
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

        if ($this->hasClickHouse() && empty($entity->_collectionPrepared)) {
            foreach ($this->getMetadata()->get("entityDefs.{$entity->getEntityName()}.links") ?? [] as $field => $defs) {
                if (!empty($defs['type']) && !empty($defs['entity']) && $defs['type'] === 'belongsTo' && !empty($entity->get($field . 'Id'))) {
                    $foreign = $this->getEntityManager()->getRepository($defs['entity'])->get($entity->get($field . 'Id'));
                    if (!empty($foreign)) {
                        $entity->set($field . 'Name', $foreign->get('name'));
                    }
                }
            }
        }
    }

    protected function hasClickHouse(): bool
    {
        return !empty($this->getConfig()->get('clickhouse')['active']) && class_exists('\ClickHouse\ORM\DB\ClickHouse\Query\QueryConverter');
    }
}
