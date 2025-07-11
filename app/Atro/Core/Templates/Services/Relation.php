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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\ORM\Entity;
use Atro\Services\Record;

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

    protected function isAssociatesRelation(): bool
    {
        return !empty($this->getAssociatesScope());
    }

    protected function getAssociatesScope()
    {
        return $this->getMetadata()->get(['scopes', $this->entityType, 'associatesForEntity']);
    }

    public function createEntity($attachment)
    {
        if ($this->isAssociatesRelation()) {
            $scope = $this->getMetadata()->get(['scopes', $this->entityType, 'associatesForEntity']);
            $pdo = $this->getEntityManager()->getPDO();

            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $inTransaction = true;
            }

            $attachments = [];
            if (!empty($attachment?->{"related{$scope}sIds"})) {
                $ids = array_unique(array_values($attachment->{"related{$scope}sIds"}));
                unset($attachment->{"related{$scope}sIds"});

                foreach ($ids as $id) {
                    $newAttachment = clone $attachment;
                    $newAttachment->{"related{$scope}Id"} = $id;
                    $attachments[] = $newAttachment;
                }

                if (!empty($attachment?->associateEverything)) {
                    while (count($ids) > 1) {
                        $mainId = array_shift($ids);
                        foreach ($ids as $id) {
                            $newAttachment = clone $attachment;
                            $newAttachment->{"main{$scope}Id"} = $mainId;
                            $newAttachment->{"related{$scope}Id"} = $id;
                            $attachments[] = $newAttachment;
                        }
                    }
                }
            } else {
                $attachments[] = $attachment;
            }

            try {
                foreach ($attachments as $attachment) {
                    $entity = parent::createEntity($attachment);
                    if (property_exists($attachment, 'backwardAssociationId') && !empty($attachment->backwardAssociationId)) {
                        try {
                            $backwardAttachment = new \stdClass();
                            $backwardAttachment->{"main{$scope}Id"} = $attachment->{"related{$scope}Id"};
                            $backwardAttachment->{"related{$scope}Id"} = $attachment->{"main{$scope}Id"};
                            $backwardAttachment->associationId = $attachment->backwardAssociationId;
                            $backwardAttachment->{"backwardAssociated{$scope}Id"} = $entity->get('id');
                            $backwardEntity = parent::createEntity($backwardAttachment);
                            $entity->set("backwardAssociated{$scope}Id", $backwardEntity->get('id'));
                            $this->getRepository()->save($entity, ['skipAll' => true]);
                        } catch (\Throwable $e) {
                            $classname = get_class($e);
                            throw new $classname(sprintf($this->getInjection('language')->translate('backwardAssociationError', 'exceptions', $scope), $e->getMessage()));
                        }
                    }
                }

                if (!empty($inTransaction)) {
                    $pdo->commit();
                }
            } catch (\Throwable $e) {
                if (!empty($inTransaction)) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            return $entity;
        }

        return Parent::createEntity($attachment);
    }

    public function updateEntity($id, $data)
    {
        if ($this->isAssociatesRelation()) {
            if (property_exists($data, '_sortedIds') && !empty($data->_sortedIds)) {
                $this->getRepository()->updateAssociatesSortOrder($data->_sortedIds);
                return $this->getEntity($id);
            }

            $pdo = $this->getEntityManager()->getPDO();

            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $inTransaction = true;
            }

            try {
                $entity = parent::updateEntity($id, $data);
                try {
                    $this->updateBackwardAssociation($entity, $data);
                } catch (\Throwable $e) {
                    $classname = get_class($e);
                    throw new $classname(sprintf($this->getInjection('language')->translate('backwardAssociationError', 'exceptions', $this->getAssociatesScope()), $e->getMessage()));
                }

                if (!empty($inTransaction)) {
                    $pdo->commit();
                }
            } catch (\Throwable $e) {
                if (!empty($inTransaction)) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            return $entity;
        }

        return Parent::updateEntity($id, $data);
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        if ($this->isAssociatesRelation()) {
            $this->prepareBackwardAssociation($entity);
        }

        return parent::isEntityUpdated($entity, $data);
    }

    protected function storeEntity(Entity $entity)
    {
        if ($this->isAssociatesRelation()) {
            try {
                $result = $this->getRepository()->save($entity, $this->getDefaultRepositoryOptions());
            } catch (UniqueConstraintViolationException $e) {
                throw new BadRequest($this->getInjection('language')->translate('productAssociationAlreadyExists', 'exceptions', $this->getAssociatesScope()));
            }

            return $result;
        }

        return Parent::storeEntity($entity);
    }

    public function updateBackwardAssociation(Entity $entity, \stdClass $data): void
    {
        $scope = $this->getAssociatesScope();
        $backwardAttachment = new \stdClass();
        $backwardIdField = "backwardAssociated{$scope}Id";

        if (property_exists($data, $backwardIdField) && !Entity::areValuesEqual('varchar', $entity->get($backwardIdField), $data->{$backwardIdField})) {
            if (!empty($entity->get($backwardIdField)) && empty($data->backwardAssociationId)) {
                // delete backward association
                $this->getRepository()->deleteFromDb($entity->get($backwardIdField));
                $entity->set($backwardIdField, null);
                $this->getRepository()->save($entity, ['skipAll' => true]);
                return;
            } elseif (empty($entity->get($backwardIdField)) && !empty($data->backwardAssociationId)) {
                // create backward association
                $backwardAttachment->{"main{$scope}Id"} = $entity->get("related{$scope}Id");
                $backwardAttachment->{"related{$scope}Id"} = $entity->get("main{$scope}Id");
                $backwardAttachment->associationId = $data->backwardAssociationId;
                $backwardAttachment->{$backwardIdField} = $entity->get('id');
                $backwardEntity = parent::createEntity($backwardAttachment);
                $entity->set($backwardIdField, $backwardEntity->get('id'));
                $this->getRepository()->save($entity, ['skipAll' => true]);
                return;
            } else {
                // update backward association
                $backwardAttachment->associationId = $data->backwardAssociationId;
            }
        }
        if (empty($entity->get($backwardIdField))) {
            return;
        }

        if (property_exists($data, "main{$scope}Id")) {
            $backwardAttachment->{"related{$scope}Id"} = $data->{"main{$scope}Id"};
        }

        if (property_exists($data, "related{$scope}Id")) {
            $backwardAttachment->{"main{$scope}Id"} = $data->{"related{$scope}Id"};
        }

        if (!empty((array)$backwardAttachment)) {
            parent::updateEntity($entity->get($backwardIdField), $backwardAttachment);
        }
    }

    public function prepareBackwardAssociation(Entity $entity): void
    {
        $scope = $this->getAssociatesScope();

        $entity->set('backwardAssociationId', null);
        $entity->set('backwardAssociationName', null);

        if (!empty($entity->get("backwardAssociated{$scope}Id"))) {
            $backwardAssociatedRecord = $this->getRepository()
                ->select(['id', 'associationId', 'associationName'])
                ->where(['id' => $entity->get("backwardAssociated{$scope}Id")])
                ->findOne();

            if (!empty($backwardAssociatedRecord)) {
                $entity->set('backwardAssociationId', $backwardAssociatedRecord->get('associationId'));
                $entity->set('backwardAssociationName', $backwardAssociatedRecord->get('associationName'));
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if ($this->isAssociatesRelation()) {
            $this->prepareBackwardAssociation($entity);
        }
    }

    public function removeAssociates(string $mainRecordId, ?string $associationId)
    {
        if (empty($mainRecordId)) {
            throw new NotFound();
        }
        $scope = $this->getAssociatesScope();

        $repository = $this->getRepository();
        $where = ["main{$scope}Id" => $mainRecordId];
        if (!empty($associationId)) {
            $where['associationId'] = $associationId;
        }
        $repository->where($where)->removeCollection();
        return true;
    }

}
