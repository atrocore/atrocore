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

namespace Atro\Core\Templates\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

/**
 * The records of a subtype of a reference data entity are the records of the supertype with the type of the subtype.
 * They are read and saved by the supertype, a subtype record contains the fields of its type only and has the ID of
 * the supertype record.
 */
class ReferenceDataSubtype extends ReferenceData
{
    public function getSupertypeName(): string
    {
        return $this->getMetadata()->get(['scopes', $this->entityName, 'supertypeId']);
    }

    /**
     * The value of the "type" field of the supertype records of the subtype.
     */
    public function getTypeOption(): string
    {
        return (string)$this->getMetadata()->get(['scopes', $this->entityName, 'typeOption']);
    }

    public function isRecordOfSubtype(?Entity $record): bool
    {
        return !empty($record) && (string)$record->get('type') === $this->getTypeOption();
    }

    /**
     * The subtype record of the supertype record, the fields of the other types are skipped.
     */
    public function createFromSupertypeRecord(Entity $record): Entity
    {
        $entity = $this->entityFactory->create($this->entityName);
        $entity->set($record->toArray());
        $entity->setAsFetched();

        return $entity;
    }

    public function find(array $params = [])
    {
        $params = array_merge($params, $this->listParams);

        if (!empty($this->whereClause)) {
            $params['whereClause'][] = $this->whereClause;
        }

        $this->whereClause = [];
        $this->listParams  = [];

        $params['whereClause'][] = ['type=' => $this->getTypeOption()];

        $entities = [];
        foreach ($this->getSupertypeRepository()->find($params) as $record) {
            $entities[] = $this->createFromSupertypeRecord($record);
        }

        $collection = new EntityCollection($entities, $this->entityName, $this->entityFactory);
        $collection->setAsFetched();

        return $collection;
    }

    public function findRelated(Entity $entity, string $link, array $selectParams): EntityCollection
    {
        $record = $this->getSupertypeRepository()->get($entity->get('id'));
        if (empty($record)) {
            return new EntityCollection();
        }

        return $this->getSupertypeRepository()->findRelated($record, $link, $selectParams);
    }

    public function countRelated(Entity $entity, string $relationName, array $params = []): int
    {
        $record = $this->getSupertypeRepository()->get($entity->get('id'));
        if (empty($record)) {
            return 0;
        }

        return $this->getSupertypeRepository()->countRelated($record, $relationName, $params);
    }

    public function save(Entity $entity, array $options = [])
    {
        $repository = $this->getSupertypeRepository();

        $record = $entity->isNew() ? $repository->get() : $repository->get($entity->get('id'));
        if (!$entity->isNew() && !$this->isRecordOfSubtype($record)) {
            throw new NotFound();
        }

        foreach ($entity->toArray() as $attribute => $value) {
            if ($entity->isNew() || $entity->isAttributeChanged($attribute)) {
                $record->set($attribute, $value);
            }
        }
        $record->set('type', $this->getTypeOption());

        $result = $repository->save($record, $options);

        $entity->set($record->toArray());
        $entity->setIsNew(false);
        $entity->setAsFetched();

        return $result;
    }

    public function remove(Entity $entity, array $options = [])
    {
        throw new BadRequest($this->translateException('subtypeCanBeDeletedViaSupertypeOnly'));
    }

    protected function getEntityById($id)
    {
        $record = $this->getSupertypeRepository()->get($id);
        if (!$this->isRecordOfSubtype($record)) {
            return null;
        }

        return $this->createFromSupertypeRecord($record);
    }

    protected function getNewEntity()
    {
        $entity = parent::getNewEntity();
        $entity->set('type', $this->getTypeOption());

        return $entity;
    }

    protected function getAllItems(array $params = []): array
    {
        $items = [];
        foreach ($this->find($params) as $entity) {
            $items[$entity->get('code')] = $entity->toArray();
        }

        return $items;
    }

    protected function getSupertypeRepository(): ReferenceData
    {
        return $this->getEntityManager()->getRepository($this->getSupertypeName());
    }
}
