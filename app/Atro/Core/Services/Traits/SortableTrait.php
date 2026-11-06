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

namespace Atro\Core\Services\Traits;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;

/**
 * The manual sort order of the records and of the related records.
 */
trait SortableTrait
{
    /**
     * Puts the records linked via $link in the order of $ids. The $ids are the records shown on a page of the
     * relation starting at $offset, the other linked records keep their order around them. The order is stored in
     * the field defined by the "sortField" parameter of the link: in the relation entity for many-to-many links and
     * in the foreign entity for one-to-many links.
     */
    public function updateRelationSortOrder(string $id, string $link, array $ids, int $offset = 0): bool
    {
        $sortField = $this->getRelationSortField($link);
        if ($sortField === null) {
            throw new BadRequest("The relation '$link' cannot be sorted.");
        }

        $entity = $this->getRepository()->get($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $this->applySortOrder($this->getRelationSortRecords($entity, $link, $sortField), $sortField, $ids, $offset);

        return true;
    }

    /**
     * Puts the records of the entity in the order of $ids. The $ids are the records shown on a page of the list
     * starting at $offset, the other records keep their order around them. The order is stored in the field defined
     * by the "collection.sortField" parameter of the entity.
     */
    public function updateSortOrder(array $ids, int $offset = 0): bool
    {
        $sortField = $this->getSortField();
        if ($sortField === null) {
            throw new BadRequest("The records of '{$this->getEntityType()}' cannot be sorted.");
        }

        if (!$this->getAcl()->check($this->getEntityType(), 'edit')) {
            throw new Forbidden();
        }

        $records = [];
        foreach ($this->getRepository()->order($sortField)->find() as $record) {
            $records[$record->get('id')] = $record;
        }

        $this->applySortOrder($records, $sortField, $ids, $offset);

        return true;
    }

    /**
     * The field that keeps the order of the records of the entity, null if the records cannot be sorted.
     */
    protected function getSortField(): ?string
    {
        return $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'collection', 'sortField']);
    }

    /**
     * Moves the $ids to $offset in the current order of $records and numbers all records in steps of 10, so that a
     * record can be put between two others by setting its sort field manually.
     *
     * @param Entity[] $records the records in the current order, keyed by the ID of the sorted record
     */
    protected function applySortOrder(array $records, string $sortField, array $ids, int $offset): void
    {
        $ids = array_values(array_unique(array_map('strval', $ids)));
        if (!empty(array_diff($ids, array_keys($records)))) {
            throw new BadRequest('Some of the records to sort do not exist.');
        }

        $sortedIds = array_values(array_diff(array_keys($records), $ids));
        array_splice($sortedIds, max(0, $offset), 0, $ids);

        foreach ($sortedIds as $k => $id) {
            $record = $records[$id];
            $sortOrder = ($k + 1) * 10;
            if ($record->get($sortField) === $sortOrder) {
                continue;
            }

            $record->set($sortField, $sortOrder);
            $this->getEntityManager()->saveEntity($record);
        }
    }

    /**
     * The records that keep the order of the records linked via $link, in the current order, keyed by the ID of the
     * linked record.
     *
     * @return Entity[]
     */
    protected function getRelationSortRecords(Entity $entity, string $link, string $sortField): array
    {
        switch ($entity->getRelationType($link)) {
            case IEntity::MANY_MANY:
                [$nearKey, $distantKey] = $entity->getRelationParam($link, 'midKeys');

                $where = array_merge(
                    $entity->getRelationParam($link, 'conditions') ?? [],
                    [$nearKey => $entity->get($entity->getRelationParam($link, 'key') ?? 'id')]
                );

                $collection = $this->getEntityManager()
                    ->getRepository(ucfirst($entity->getRelationParam($link, 'relationName')))
                    ->where($where)
                    ->order($sortField)
                    ->find();
                break;
            case IEntity::HAS_MANY:
                $distantKey = 'id';
                $foreignKey = $entity->getRelationParam($link, 'foreignKey') ?? $entity->getRelationParam($link, 'foreign') . 'Id';
                $collection = $this->getEntityManager()
                    ->getRepository($entity->getRelationParam($link, 'entity'))
                    ->where([$foreignKey => $entity->get('id')])
                    ->order($sortField)
                    ->find();
                break;
            default:
                throw new BadRequest("The relation '$link' cannot be sorted.");
        }

        $records = [];
        foreach ($collection as $record) {
            $records[(string)$record->get($distantKey)] = $record;
        }

        return $records;
    }
}
