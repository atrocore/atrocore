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

    public function createEntity(\stdClass $attachment): string
    {
        if ($this->isAssociatesRelation()) {
            if (empty($attachment?->associatedItemsIds) && empty($attachment?->associatedItemId)) {
                throw new BadRequest("Either 'associatedItemId' or 'associatedItemsIds' is required.");
            }

            $scope = $this->getMetadata()->get(['scopes', $this->entityType, 'associatesForEntity']);
            $pdo = $this->getEntityManager()->getPDO();

            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $inTransaction = true;
            }

            $attachments = [];
            if (!empty($attachment?->{"associatedItemsIds"})) {
                $ids = array_unique(array_values($attachment->{"associatedItemsIds"}));
                unset($attachment->{"associatedItemsIds"});

                foreach ($ids as $id) {
                    $newAttachment = clone $attachment;
                    $newAttachment->associatedItemId = $id;
                    $attachments[] = $newAttachment;
                }

                if (!empty($attachment?->associateEverything)) {
                    while (count($ids) > 1) {
                        $mainId = array_shift($ids);
                        foreach ($ids as $id) {
                            $newAttachment = clone $attachment;
                            $newAttachment->associatingItemId = $mainId;
                            $newAttachment->associatedItemId = $id;
                            $attachments[] = $newAttachment;
                        }
                    }
                }
            } else {
                $attachments[] = $attachment;
            }

            try {
                foreach ($attachments as $attachment) {
                    $id = parent::createEntity($attachment);
                    $entity = $this->getRepository()->get($id);
                    if (property_exists($attachment, 'reverseAssociationId') && !empty($attachment->reverseAssociationId)) {
                        try {
                            $reverseAttachment = new \stdClass();
                            $reverseAttachment->associatingItemId = $attachment->associatedItemId;
                            $reverseAttachment->associatedItemId = $attachment->associatingItemId;
                            $reverseAttachment->associationId = $attachment->reverseAssociationId;
                            $reverseAttachment->{"reverseAssociated{$scope}Id"} = $entity->get('id');
                            $reverseId = parent::createEntity($reverseAttachment);
                            $entity->set("reverseAssociated{$scope}Id", $reverseId);
                            $this->getRepository()->save($entity, ['skipAll' => true]);
                        } catch (\Throwable $e) {
                            $classname = get_class($e);
                            throw new $classname(sprintf($this->getInjection('language')->translate('reverseAssociationError', 'exceptions', $scope), $e->getMessage()));
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

            return $entity->get('id');
        }

        return Parent::createEntity($attachment);
    }

    public function updateEntity(string $id, \stdClass $data): bool
    {
        if ($this->isAssociatesRelation()) {
            if (property_exists($data, '_sortedIds') && !empty($data->_sortedIds)) {
                $this->getRepository()->updateAssociatesSortOrder($data->_sortedIds);
                return true;
            }

            $pdo = $this->getEntityManager()->getPDO();

            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $inTransaction = true;
            }

            try {
                parent::updateEntity($id, $data);
                $entity = $this->getRepository()->get($id);

                if (!property_exists($data, '__skipUpdateReverse')) {
                    try {
                        $this->updateReverseAssociation($entity, $data);
                    } catch (\Throwable $e) {
                        $classname = get_class($e);
                        throw new $classname(sprintf($this->getInjection('language')->translate('reverseAssociationError', 'exceptions', $this->getAssociatesScope()), $e->getMessage()));
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

            return true;
        }

        return Parent::updateEntity($id, $data);
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        if ($this->isAssociatesRelation()) {
            $this->prepareReverseAssociation($entity);
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

    public function updateReverseAssociation(Entity $entity, \stdClass $data): void
    {
        $scope = $this->getAssociatesScope();
        $reverseAttachment = new \stdClass();
        $reverseIdField = "reverseAssociated{$scope}Id";

        if (!empty($entity->get($reverseIdField)) && $entity->isAttributeChanged('reverseAssociationId') && empty($data->reverseAssociationId)) {
            // delete reverse association
            $this->getRepository()->deleteFromDb($entity->get($reverseIdField));
            $entity->set($reverseIdField, null);
            $this->getRepository()->save($entity, ['skipAll' => true]);
            return;
        } elseif (empty($entity->get($reverseIdField)) && $entity->isAttributeChanged('reverseAssociationId') && !empty($data->reverseAssociationId)) {
            // create reverse association
            $reverseAttachment->associatingItemId = $entity->get("associatedItemId");
            $reverseAttachment->associatedItemId = $entity->get("associatingItemId");
            $reverseAttachment->associationId = $data->reverseAssociationId;
            $reverseAttachment->{$reverseIdField} = $entity->get('id');
            $reverseId = parent::createEntity($reverseAttachment);
            $entity->set($reverseIdField, $reverseId);
            $this->getRepository()->save($entity, ['skipAll' => true]);
            return;
        }

        if (empty($entity->get($reverseIdField))) {
            return;
        }

        if (property_exists($data, 'reverseAssociationId')) {
            $reverseAttachment->associationId = $data->reverseAssociationId;
        }

        if (property_exists($data, "associatingItemId")) {
            $reverseAttachment->associatedItemId = $data->associatingItemId;
        }

        if (property_exists($data, "associatedItemId")) {
            $reverseAttachment->associatingItemId = $data->associatedItemId;
        }

        if (!empty((array)$reverseAttachment)) {
            $reverseAttachment->__skipUpdateReverse = true;
            parent::updateEntity($entity->get($reverseIdField), $reverseAttachment);
        }
    }

    public function prepareReverseAssociation(Entity $entity): void
    {
        $scope = $this->getAssociatesScope();

        $entity->set('reverseAssociationId', null);
        $entity->set('reverseAssociationName', null);

        if (!empty($entity->get("reverseAssociated{$scope}Id"))) {
            $reverseAssociatedRecord = $this->getRepository()
                ->select(['id', 'associationId', 'associationName'])
                ->where(['id' => $entity->get("reverseAssociated{$scope}Id")])
                ->findOne();

            if (!empty($reverseAssociatedRecord)) {
                $entity->set('reverseAssociationId', $reverseAssociatedRecord->get('associationId'));
                $entity->set('reverseAssociationName', $reverseAssociatedRecord->get('associationName'));
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if ($this->isAssociatesRelation()) {
            $this->prepareReverseAssociation($entity);
        }
    }

    public function removeAssociates(string $mainRecordId, string $relatedRecordId, ?string $associationId)
    {
        if (empty($mainRecordId) && empty($relatedRecordId)) {
            throw new NotFound();
        }

        $repository = $this->getRepository();
        $where = [];
        if (!empty($mainRecordId)) {
            $where["associatingItemId"] = $mainRecordId;
        }
        if (!empty($relatedRecordId)) {
            $where["associatedItemId"] = $relatedRecordId;
        }
        if (!empty($associationId)) {
            $where['associationId'] = $associationId;
        }
        $repository->where($where)->removeCollection();
        return true;
    }

}
