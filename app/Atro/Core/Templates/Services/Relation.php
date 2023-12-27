<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Templates\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\Services\Record;

class Relation extends Record
{
    public function inheritRelation(\stdClass $data): Entity
    {
        if (!property_exists($data, 'entityType') || !property_exists($data, 'entityId') || !property_exists($data, 'relation') || !property_exists($data, 'relId')) {
            throw new BadRequest('Invalid input data.');
        }

        $mainEntity = $this->getEntityManager()->getRepository($data->entityType)->get($data->entityId);
        if (empty($mainEntity)) {
            throw new NotFound();
        }

        $keySet = $this->getRepository()->getMapper()->getKeys($mainEntity, $data->relation);

        $entity = $this->getRepository()
            ->where([
                $keySet['nearKey']    => $mainEntity->get('id'),
                $keySet['distantKey'] => $data->relId
            ])
            ->findOne();

        if (empty($entity)) {
            throw new NotFound();
        }

        $additionalFields = $this->getRepository()->getAdditionalFieldsNames();
        if (empty($additionalFields)) {
            return $entity;
        }

        $parentsIds = $mainEntity->getLinkMultipleIdList('parents');
        if (empty($parentsIds[0])) {
            return $entity;
        }

        $parentCollection = $this->getRepository()
            ->where([
                $keySet['nearKey']    => $parentsIds,
                $keySet['distantKey'] => $data->relId
            ])
            ->find();

        foreach ($parentCollection as $parentItem) {
            $input = new \stdClass();
            foreach ($additionalFields as $additionalField) {
                $input->{$additionalField} = $parentItem->get($additionalField);
            }

            return $this->updateEntity($entity->get('id'), $input);
        }

        return $entity;
    }

    protected function afterCreateEntity(Entity $entity, $data)
    {
        $this->createHierarchical($entity);

        parent::afterCreateEntity($entity, $data);
    }

    public function createHierarchical(Entity $entity): void
    {
        if (!$this->getRepository()->isInheritedRelation()) {
            return;
        }

        $link = $this->getRepository()->getHierarchicalRelation();
        if (empty($link)) {
            return;
        }

        $hierarchicalEntity = $entity->get($link);
        if (empty($hierarchicalEntity)) {
            return;
        }

        $children = $this->getEntityManager()->getRepository($hierarchicalEntity->getEntityType())->getChildrenRecursivelyArray($hierarchicalEntity->get('id'));
        if (empty($children)) {
            return;
        }

        $additionalFields = $this->getRepository()->getAdditionalFieldsNames();

        foreach ($children as $childId) {
            $input = new \stdClass();
            foreach ($this->getRepository()->getRelationFields() as $relField) {
                $input->{"{$relField}Id"} = $relField === $link ? $childId : $entity->get("{$relField}Id");
            }
            foreach ($additionalFields as $additionalField) {
                $input->{$additionalField} = $entity->get($additionalField);
            }
            $parentId = $this->getPseudoTransactionManager()->pushCreateEntityJob($entity->getEntityType(), $input);
            $this->getPseudoTransactionManager()->pushUpdateEntityJob($hierarchicalEntity->getEntityType(), $hierarchicalEntity->get('id'), null, $parentId);
        }
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        $this->updateHierarchical($entity, $data);

        parent::afterUpdateEntity($entity, $data);
    }

    protected function afterDeleteEntity(Entity $entity)
    {
        $this->deleteHierarchical($entity);

        parent::afterDeleteEntity($entity);
    }

    public function updateHierarchical(Entity $entity, \stdClass $data): void
    {
        if (!$this->getRepository()->isInheritedRelation()) {
            return;
        }

        $childrenRecords = $this->getRepository()->getChildren($entity->_fetchedEntity);
        if ($childrenRecords === null) {
            return;
        }

        foreach ($childrenRecords as $childrenRecord) {
            try {
                $this->updateEntity($childrenRecord->get('id'), clone $data);
            } catch (Forbidden $e) {
            } catch (NotFound $e) {
            } catch (BadRequest $e) {
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('updateHierarchical failed: ' . $e->getMessage());
            }
        }
    }

    public function deleteHierarchical(Entity $entity): void
    {
        if (!$this->getRepository()->isInheritedRelation()) {
            return;
        }

        $childrenRecords = $this->getRepository()->getChildren($entity);
        if ($childrenRecords === null) {
            return;
        }

        foreach ($childrenRecords as $childrenRecord) {
            try {
                $this->deleteEntity($childrenRecord->get('id'));
            } catch (Forbidden $e) {
            } catch (NotFound $e) {
            } catch (BadRequest $e) {
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('deleteHierarchical failed: ' . $e->getMessage());
            }
        }
    }
}
