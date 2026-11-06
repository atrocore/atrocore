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
use Atro\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Atro\Core\Services\AbstractRecordService;
use Atro\Core\Services\Interfaces\EditableInterface;
use Atro\Core\Services\Interfaces\LinkableInterface;
use Atro\Core\Services\Interfaces\SortableInterface;
use Atro\Core\Services\Interfaces\DeletableInterface;
use Atro\Core\Services\Traits\EditableTrait;
use Atro\Core\Services\Traits\LinkableTrait;
use Atro\Core\Services\Traits\SortableTrait;
use Atro\Core\Services\Traits\DeletableTrait;

/**
 * The service of the entities of type ReferenceData: the records are stored in a file, the relations are kept as
 * the lists of the IDs of the related records.
 */
class ReferenceData extends AbstractRecordService implements
    EditableInterface,
    LinkableInterface,
    SortableInterface,
    DeletableInterface
{
    use EditableTrait;
    use LinkableTrait;
    use SortableTrait;
    use DeletableTrait;

    public function linkEntity($id, $link, $foreignId)
    {
        $defs = $this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link]);
        $entity = $this->getEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        if (!empty($defs) && !empty($defs['entity']) && $defs['type'] === 'hasMany') {
            $foreign = $this->getEntityManager()->getEntity($defs['entity'], $foreignId);
            if (empty($foreign)) {
                throw new NotFound();
            }

            $idsField = $link . 'Ids';
            $namesField = $link . 'Names';

            $value = array_values($entity->get($idsField) ?? []);
            $value[] = $foreignId;
            $valueNames = $entity->get($namesField) ?? new \stdClass();
            $valueNames->{$foreignId} = $foreign->get('name');

            $entity->set($idsField, $value);
            $entity->set($namesField, $valueNames);
            $this->getRepository()->save($entity);

            return true;
        }

        throw new BadRequest('Not implemented');
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        $defs = $this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link]);
        $entity = $this->getEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        if (!empty($defs) && !empty($defs['entity']) && $defs['type'] === 'hasMany') {
            $foreign = $this->getEntityManager()->getEntity($defs['entity'], $foreignId);
            if (empty($foreign)) {
                throw new NotFound();
            }

            $idsField = $link . 'Ids';
            $namesField = $link . 'Names';

            $value = $entity->get($idsField) ?? [];
            $index = array_search($foreignId, $value);
            if ($index !== false) {
                unset($value[$index]);
                $value = array_values($value);
            }

            $entity->set($idsField, $value);
            if (!empty($valueNames = $entity->get($namesField)) && is_object($valueNames)) {
                unset($valueNames->{$foreignId});
                $entity->set($namesField, $valueNames);
            }
            $this->getRepository()->save($entity);

            return true;
        }

        throw new BadRequest('Not implemented');
    }

    public function unlinkAll(string $id, string $link): bool
    {
        $defs = $this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link]);
        $entity = $this->getEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        if (!empty($defs) && !empty($defs['entity']) && $defs['type'] === 'hasMany') {
            $entity->set($link . 'Ids', []);
            $entity->set($link . 'Names', []);
            $this->getRepository()->save($entity);

            return true;
        }

        throw new BadRequest('Not implemented');
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $foreigns = [];

        foreach ($this->getMetadata()->get(['entityDefs', $collection->getEntityName(), 'links'], []) as $link => $defs) {
            if ($defs['type'] === 'belongsTo' && !empty($defs['entity']) && $this->getMetadata()->get(['scopes', $defs['entity'], 'type']) !== 'ReferenceData') {
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
            foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'links'], []) as $link => $defs) {
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
