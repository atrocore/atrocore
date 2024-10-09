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

use Atro\Core\Exceptions\BadRequest;
use Atro\Services\Record;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class ReferenceData extends Record
{
    public function findLinkedEntities($id, $link, $params)
    {
        throw new BadRequest();
    }

    public function linkEntity($id, $link, $foreignId)
    {
        throw new BadRequest();
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        throw new BadRequest();
    }

    public function linkEntityMass($id, $link, $where, $selectData = null)
    {
        throw new BadRequest();
    }

    public function unlinkAll(string $id, string $link): bool
    {
        throw new BadRequest();
    }

    public function massUpdate($data, array $params)
    {
        throw new BadRequest();
    }

    public function follow($id, $userId = null)
    {
        throw new BadRequest();
    }

    public function unfollow($id, $userId = null)
    {
        throw new BadRequest();
    }

    public function massFollow(array $params, $userId = null)
    {
        throw new BadRequest();
    }

    public function massUnfollow(array $params, $userId = null)
    {
        throw new BadRequest();
    }

    public function massRemove(array $params)
    {
        throw new BadRequest();
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $foreigns = [];

        foreach ($this->getMetadata()->get(['entityDefs', $collection->getEntityName(), 'links']) as $link => $defs) {
            if ($defs['type'] === 'belongsTo' && !empty($defs['entity'])) {
                $foreigns[$link] = $this->getEntityManager()
                    ->getRepository($defs['entity'])
                    ->where([
                        'id' => array_column($collection->toArray(), "{$link}Id")
                    ])
                    ->find();
            }
        }

        foreach ($collection as $entity) {
            $entity->_preparedForOutput = true;
            foreach ($foreigns as $link => $foreignCollection) {
                if (!empty($entity->get("{$link}Id"))) {
                    foreach ($foreignCollection as $foreign) {
                        if ($foreign->get('id') === $entity->get("{$link}Id")) {
                            $entity->set("{$link}Name", $foreign->get('name'));
                            break;
                        }
                    }
                }
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_preparedForOutput)) {
            foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'links']) as $link => $defs) {
                if (!empty($entity->get("{$link}Id")) && $defs['type'] === 'belongsTo' && !empty($defs['entity'])) {
                    $foreign = $this->getEntityManager()->getEntity($defs['entity'], $entity->get("{$link}Id"));
                    if (!empty($foreign)) {
                        $entity->set("{$link}Name", $foreign->get('name'));
                    }
                }
            }
        }
    }
}
