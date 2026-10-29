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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;

/**
 * Stores the type-specific data of a supertype record. A subtype record contains the fields of its supertype
 * record too: they are read through the "supertype" relation and are saved into the supertype record. The table
 * has no "deleted" column: a subtype record shares the lifecycle of its supertype record and is removed by the
 * database together with it.
 */
class Subtype extends Base
{
    public function save(Entity $entity, array $options = [])
    {
        $connection = $this->getConnection();

        $connection->beginTransaction();
        try {
            $supertypeRecord = $this->saveSupertypeRecord($entity, $options);

            $result = parent::save($entity, $options);

            $this->populateFromSupertypeRecord($entity, $supertypeRecord);

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $result;
    }

    public function remove(Entity $entity, array $options = [])
    {
        throw new BadRequest($this->translateException('subtypeCanBeDeletedViaSupertypeOnly'));
    }

    public function hasDeletedRecordsToClear(): bool
    {
        return false;
    }

    public function clearDeletedRecords(): void
    {
    }

    public function getLinkedIds(string $field, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $column = $this->getMapper()->toDb($field);

        $rows = $this->createSupertypeJoinQuery()
            ->select('t.id', 't.' . $this->getDbal()->quoteIdentifier($column) . ' AS foreign_id')
            ->andWhere('t.id IN (:ids)')
            ->andWhere('t.' . $this->getDbal()->quoteIdentifier($column) . ' IS NOT NULL')
            ->setParameter('ids', array_values($ids), Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        return array_column($rows, 'foreign_id', 'id');
    }

    public function getReferencingIds(string $field, array $referencedIds): array
    {
        if (empty($referencedIds)) {
            return [];
        }

        $column = $this->getMapper()->toDb($field);

        $rows = $this->createSupertypeJoinQuery()
            ->select('t.id', 't.' . $this->getDbal()->quoteIdentifier($column) . ' AS foreign_id')
            ->andWhere('t.' . $this->getDbal()->quoteIdentifier($column) . ' IN (:referencedIds)')
            ->orderBy('t.id')
            ->setParameter('referencedIds', array_values($referencedIds), Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        return array_column($rows, 'foreign_id', 'id');
    }

    /**
     * The subtype records whose supertype records are not deleted.
     */
    protected function createSupertypeJoinQuery()
    {
        $dbal = $this->getDbal();

        return $dbal->createQueryBuilder()
            ->from($dbal->quoteIdentifier($this->getMapper()->toDb($this->entityType)), 't')
            ->innerJoin('t', $dbal->quoteIdentifier($this->getMapper()->toDb($this->getSupertypeName())), 's', 's.id = t.supertype_id')
            ->where('s.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN);
    }

    public function getSupertypeName(): string
    {
        return $this->getMetadata()->get(['scopes', $this->entityType, 'supertypeId']);
    }

    /**
     * The value of the "type" field of the supertype record that points to this subtype.
     */
    public function getTypeOption(): string
    {
        return lcfirst(substr($this->entityType, strlen($this->getSupertypeName() . 'Type')));
    }

    /**
     * The attributes of the subtype record that are stored in the supertype record.
     */
    public function getSupertypeAttributes(Entity $entity): array
    {
        $attributes = [];
        foreach ($entity->getAttributes() as $attribute => $defs) {
            if (
                ($defs['type'] ?? null) === IEntity::FOREIGN
                && ($defs['relation'] ?? null) === 'supertype'
                && !empty($defs['proxyType'])
                && !in_array($attribute, ['deleted', 'type'], true)
            ) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    protected function saveSupertypeRecord(Entity $entity, array $options): Entity
    {
        $repository = $this->getEntityManager()->getRepository($this->getSupertypeName());

        if ($entity->isNew()) {
            $supertypeRecord = $repository->get();
            $supertypeRecord->set('type', $this->getTypeOption());
        } else {
            $supertypeRecord = $repository->get($entity->get('supertypeId'));
            if (empty($supertypeRecord)) {
                throw new NotFound($this->translateException('supertypeRecordIsNotFound'));
            }
        }

        foreach ($this->getSupertypeAttributes($entity) as $attribute) {
            if ($entity->has($attribute) && ($entity->isNew() || $entity->isAttributeChanged($attribute))) {
                $supertypeRecord->set($attribute, $entity->get($attribute));
            }
        }

        $this->attachSubtypeFields($entity, $supertypeRecord);

        // the supertype record is saved every time, so that the record is marked as modified
        $this->getEntityManager()->saveEntity($supertypeRecord, $options);

        $entity->set('supertypeId', $supertypeRecord->get('id'));

        return $supertypeRecord;
    }

    /**
     * The stream exists for the supertype record only, so the fields of the subtype are attached to the supertype
     * record as not storable ones: the changes of the record are tracked in one place.
     */
    protected function attachSubtypeFields(Entity $entity, Entity $supertypeRecord): void
    {
        $supertypeRecord->subtypeName = $this->entityType;

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $defs) {
            if (empty($defs['type']) || !empty($defs['supertypeField']) || in_array($field, ['supertype', '_meta'], true)) {
                continue;
            }

            if (in_array($defs['type'], ['link', 'linkMultiple'], true) && empty($defs['entity'])) {
                $defs['entity'] = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'entity']);
            }

            $supertypeRecord->entityDefs['fields'][$field] = $defs;
        }

        foreach ($entity->getAttributes() as $attribute => $defs) {
            if (isset($supertypeRecord->fields[$attribute]) || in_array($attribute, ['id', 'supertypeId', 'supertypeName'], true)) {
                continue;
            }

            $supertypeRecord->fields[$attribute] = array_merge($defs, ['notStorable' => true]);

            if ($entity->hasFetched($attribute)) {
                $supertypeRecord->setFetched($attribute, $entity->getFetched($attribute));
            }
            if ($entity->has($attribute)) {
                $supertypeRecord->set($attribute, $entity->get($attribute));
            }
        }
    }

    protected function populateFromSupertypeRecord(Entity $entity, Entity $supertypeRecord): void
    {
        foreach ($this->getSupertypeAttributes($entity) as $attribute) {
            if ($supertypeRecord->has($attribute)) {
                $entity->set($attribute, $supertypeRecord->get($attribute));
            }
        }
        $entity->set('type', $supertypeRecord->get('type'));
    }
}
