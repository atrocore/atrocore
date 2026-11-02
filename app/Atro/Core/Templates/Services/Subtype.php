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

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\NotFound;
use Atro\Services\Record;
use Espo\ORM\Entity;

/**
 * A subtype record is the supertype record extended with the subtype fields, the repository splits the data
 * between both tables. The relations of the supertype are served by the supertype record.
 */
class Subtype extends Record
{
    public function loadAdditionalFields(Entity $entity, bool $skipLinkMultiple = false)
    {
        parent::loadAdditionalFields($entity, $skipLinkMultiple);

        if (!$skipLinkMultiple) {
            $this->loadSupertypeLinkMultipleFields($entity, $this->getSupertypeLinkMultipleFields(true));
        }
    }

    protected function loadWithRelationships(Entity $entity, string $withRelationships): void
    {
        parent::loadWithRelationships($entity, $withRelationships);

        $fields = array_intersect(
            array_filter(array_map('trim', explode(',', $withRelationships))),
            $this->getSupertypeLinkMultipleFields(false)
        );

        $this->loadSupertypeLinkMultipleFields($entity, $fields);
    }

    public function findLinkedEntities($id, $link, $params)
    {
        if ($this->isSupertypeLink($link)) {
            return $this->getSupertypeService()->findLinkedEntities($this->getSupertypeRecordId($id), $link, $params);
        }

        return parent::findLinkedEntities($id, $link, $params);
    }

    public function linkEntity($id, $link, $foreignId)
    {
        if ($this->isSupertypeLink($link)) {
            return $this->getSupertypeService()->linkEntity($this->getSupertypeRecordId($id), $link, $foreignId);
        }

        return parent::linkEntity($id, $link, $foreignId);
    }

    public function duplicateAndLinkEntity($id, $link, $foreignId): bool
    {
        if ($this->isSupertypeLink($link)) {
            return $this->getSupertypeService()->duplicateAndLinkEntity($this->getSupertypeRecordId($id), $link, $foreignId);
        }

        return parent::duplicateAndLinkEntity($id, $link, $foreignId);
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        if ($this->isSupertypeLink($link)) {
            return $this->getSupertypeService()->unlinkEntity($this->getSupertypeRecordId($id), $link, $foreignId);
        }

        return parent::unlinkEntity($id, $link, $foreignId);
    }

    public function linkEntityMass($id, $link, $where, $selectData = null)
    {
        if ($this->isSupertypeLink($link)) {
            return $this->getSupertypeService()->linkEntityMass($this->getSupertypeRecordId($id), $link, $where, $selectData);
        }

        return parent::linkEntityMass($id, $link, $where, $selectData);
    }

    public function duplicateAndLinkEntityMass($id, $link, $where, $selectData = null)
    {
        if ($this->isSupertypeLink($link)) {
            return $this->getSupertypeService()->duplicateAndLinkEntityMass($this->getSupertypeRecordId($id), $link, $where, $selectData);
        }

        return parent::duplicateAndLinkEntityMass($id, $link, $where, $selectData);
    }

    public function unlinkAll(string $id, string $link): bool
    {
        if ($this->isSupertypeLink($link)) {
            return $this->getSupertypeService()->unlinkAll($this->getSupertypeRecordId($id), $link);
        }

        return parent::unlinkAll($id, $link);
    }

    protected function filterInput($data, ?Entity $entity = null)
    {
        parent::filterInput($data, $entity);

        if (!is_object($data)) {
            return;
        }

        // the supertype record is linked by the repository and the subtype is defined by the entity itself
        unset($data->supertypeId, $data->supertypeName, $data->type);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $this->loadSupertypeLinkNames($entity);
    }

    /**
     * A subtype record is the supertype record, the listeners of the supertype service apply to it as well.
     */
    protected function dispatchEvent(string $action, Event $event): Event
    {
        $event = parent::dispatchEvent($action, $event);

        return $this->getInjection('eventManager')->dispatch($this->getSupertypeName() . 'Service', $action, $event);
    }

    protected function isSupertypeLink(string $link): bool
    {
        return !empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $link, 'supertypeField']));
    }

    protected function getSupertypeName(): string
    {
        return $this->getMetadata()->get(['scopes', $this->entityType, 'supertypeId']);
    }

    /**
     * @return Record
     */
    protected function getSupertypeService()
    {
        return $this->getServiceFactory()->create($this->getSupertypeName());
    }

    protected function getSupertypeRecordId(string $id): string
    {
        $record = $this->getRepository()->get($id);
        if (empty($record) || empty($record->get('supertypeId'))) {
            throw new NotFound();
        }

        return $record->get('supertypeId');
    }

    /**
     * The relations to many records of the supertype, the ones that are loaded together with the record when
     * $onlyLoaded is true.
     */
    protected function getSupertypeLinkMultipleFields(bool $onlyLoaded): array
    {
        $fields = [];
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $defs) {
            if (empty($defs['supertypeField']) || ($defs['type'] ?? null) !== 'linkMultiple') {
                continue;
            }

            if ($onlyLoaded && !empty($defs['noLoad']) && $field !== 'teams') {
                continue;
            }

            $fields[] = $field;
        }

        return $fields;
    }

    protected function loadSupertypeLinkMultipleFields(Entity $entity, array $fields): void
    {
        if (empty($fields) || empty($entity->get('supertypeId'))) {
            return;
        }

        $supertypeRecord = $this->getEntityManager()->getRepository($this->getSupertypeName())->get($entity->get('supertypeId'));
        if (empty($supertypeRecord)) {
            return;
        }

        foreach ($fields as $field) {
            $columns = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field, 'columns']);
            $supertypeRecord->loadLinkMultipleField($field, $columns);

            foreach (['Ids', 'Names', 'Columns'] as $suffix) {
                if ($supertypeRecord->has($field . $suffix)) {
                    $entity->set($field . $suffix, $supertypeRecord->get($field . $suffix));
                }
            }
        }
    }

    /**
     * The names of the records linked to the supertype record are not joined, they are loaded separately.
     */
    protected function loadSupertypeLinkNames(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $defs) {
            if (empty($defs['supertypeField']) || ($defs['type'] ?? null) !== 'link') {
                continue;
            }

            $id = $entity->get($field . 'Id');
            if (empty($id) || !empty($entity->get($field . 'Name'))) {
                continue;
            }

            $foreignEntity = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'entity']);
            if (empty($foreignEntity)) {
                continue;
            }

            $record = $this->getEntityManager()->getEntity($foreignEntity, $id);
            if (!empty($record)) {
                $entity->set($field . 'Name', $record->get($this->getNameField($foreignEntity)));
            }
        }
    }
}
