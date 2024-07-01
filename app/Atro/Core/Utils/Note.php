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

namespace Atro\Core\Utils;

use Atro\Core\Container;

use Doctrine\DBAL\ParameterType;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Preferences;
use Espo\ORM\Entity as OrmEntity;
use Espo\Services\Stream as StreamService;
use Espo\Core\Utils\FieldManager;
use Espo\Entities\User;

class Note
{
    private Container $container;
    private array $streamEnabled = [];
    private array $relationEntityData = [];
    private array $createRelatedData = [];
    private array $auditedFieldsCache = [];
    private ?bool $followCreatedEntities = null;
    private ?StreamService $streamService = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function afterEntitySaved(OrmEntity $entity): void
    {
        if ($entity->isNew()) {
            $this->handleRelationEntity($entity, 'Relate');
            if ($this->streamEnabled($entity->getEntityType())) {
                $this->followCreatedEntity($entity);
            }
        }

        if ($this->streamEnabled($entity->getEntityType()) && !$entity->isNew()) {
            $this->handleAudited($entity);
        }

        $this->handleCreateRelated($entity);
    }

    public function afterEntityRemoved(OrmEntity $entity): void
    {
        $this->handleRelationEntity($entity, 'Unrelate');

        if ($this->streamEnabled($entity->getEntityType())) {
            $this->getStreamService()->unfollowAllUsersFromEntity($entity);
        }

        $conn = $this->getEntityManager()->getConnection();
        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier('note'))
            ->set('deleted', ':deleted')
            ->where("parent_type = :entityType")
            ->andWhere("parent_id = :entityId")
            ->setParameter('entityId', $entity->id)
            ->setParameter('entityType', $entity->getEntityType())
            ->setParameter('deleted', true, ParameterType::BOOLEAN)
            ->executeQuery();
    }

    protected function streamEnabled(string $entityType): bool
    {
        if (!isset($this->streamEnabled[$entityType])) {
            $this->streamEnabled[$entityType] = empty($this->getMetadata()->get("scopes.{$entityType}.streamDisabled"));
        }

        return $this->streamEnabled[$entityType];
    }

    protected function followCreatedEntity(OrmEntity $entity): void
    {
        $userIdList = [];
        if ($this->isFollowCreatedEntities() && $entity->get('createdById') && $entity->get('createdById') === $this->getUser()->id) {
            $this->getStreamService()->followEntity($entity, $entity->get('createdById'));
            $userIdList[] = $entity->get('createdById');
        }
        if (!empty($entity->get('assignedUserId')) && !in_array($entity->get('assignedUserId'), $userIdList)) {
            $this->getStreamService()->followEntity($entity, $entity->get('assignedUserId'));
            $userIdList[] = $entity->get('assignedUserId');
        }

        if (in_array($this->getUser()->id, $userIdList)) {
            $entity->set('isFollowed', true);
        }
    }

    protected function getAuditedFieldsData(OrmEntity $entity): array
    {
        $entityType = $entity->getEntityType();

        if (!array_key_exists($entityType, $this->auditedFieldsCache)) {
            $auditableTypes = [];
            foreach ($this->getMetadata()->get('fields') as $type => $typeData) {
                if (!empty($typeData['auditable'])) {
                    $auditableTypes[] = $type;
                }
            }

            $systemFields = ['id', 'deleted', 'createdAt', 'modifiedAt', 'createdBy'];

            $fields = $this->getMetadata()->get('entityDefs.' . $entityType . '.fields');

            $auditedFields = [];
            foreach ($fields as $field => $d) {
                if (!empty($d['type']) && in_array($d['type'], $auditableTypes) && !in_array($field, $systemFields) && empty($d['notStorable'])) {
                    $auditedFields[$field]['actualList'] = $this->getFieldManager()->getActualAttributeList($entityType, $field);
                    $auditedFields[$field]['notActualList'] = $this->getFieldManager()->getNotActualAttributeList($entityType, $field);
                    $auditedFields[$field]['fieldType'] = $d['type'];
                }
            }
            $this->auditedFieldsCache[$entityType] = $auditedFields;
        }

        return $this->auditedFieldsCache[$entityType];
    }

    protected function handleAudited(OrmEntity $entity): void
    {
        $auditedFields = $this->getAuditedFieldsData($entity);

        $updatedFieldList = [];
        $was = [];
        $became = [];

        foreach ($auditedFields as $field => $item) {
            $updated = false;
            foreach ($item['actualList'] as $attribute) {
                if ($entity->hasFetched($attribute) && $entity->isAttributeChanged($attribute)) {
                    $updated = true;
                }
            }
            if ($updated) {
                $updatedFieldList[] = $field;

                foreach (['actualList', 'notActualList'] as $key) {
                    foreach ($item[$key] as $attribute) {
                        if ($entity->isAttributeChanged($attribute)) {
                            $valueWas = $entity->getFetched($attribute);
                            $valueBecame = $entity->get($attribute);

                            if (!(($valueWas === null || $valueWas === '') && ($valueBecame === null || $valueBecame === ''))) {
                                $was[$attribute] = $valueWas;
                                $became[$attribute] = $valueBecame;
                            }
                        }
                    }
                }

                if ($item['fieldType'] === 'linkParent') {
                    $wasParentType = $was[$field . 'Type'];
                    $wasParentId = $was[$field . 'Id'];
                    if ($wasParentType && $wasParentId) {
                        if ($this->getEntityManager()->hasRepository($wasParentType)) {
                            $wasParent = $this->getEntityManager()->getEntity($wasParentType, $wasParentId);
                            if ($wasParent) {
                                $was[$field . 'Name'] = $wasParent->get('name');
                            }
                        }
                    }
                }
            }
        }

        if (empty($was) && empty($became)) {
            return;
        }

        if (!empty($updatedFieldList)) {
            $note = $this->getEntityManager()->getEntity('Note');
            $note->set('type', 'Update');
            $note->set('parentId', $entity->id);
            $note->set('parentType', $entity->getEntityType());

            $note->set('data', [
                'fields'     => $updatedFieldList,
                'attributes' => [
                    'was'    => $was,
                    'became' => $became
                ]
            ]);

            $this->getEntityManager()->saveEntity($note);
        }
    }

    protected function handleCreateRelated(OrmEntity $entity): void
    {
        if (!$this->streamEnabled($entity->getEntityType())) {
            return;
        }

        if (!isset($this->createRelatedData[$entity->getEntityType()])) {
            $this->createRelatedData[$entity->getEntityType()] = [];
            foreach ($this->getMetadata()->get("entityDefs." . $entity->getEntityType() . ".links", []) as $link => $defs) {
                if ($defs['type'] == 'belongsTo') {
                    if (empty($defs['foreign']) || empty($defs['entity'])) {
                        continue;
                    }
                    if (!empty($defs['entity'])) {
                        if (!$this->streamEnabled($defs['entity'])) {
                            continue;
                        }
                        $this->createRelatedData[$entity->getEntityType()][$link . 'Id'] = $defs['entity'];
                    }
                }
            }
        }

        foreach ($this->createRelatedData[$entity->getEntityType()] as $field => $scope) {
            if ($entity->isAttributeChanged($field) && !empty($entity->get($field))) {
                $note = $this->getEntityManager()->getEntity('Note');
                $note->set('type', 'CreateRelated');
                $note->set('parentId', $entity->get($field));
                $note->set('parentType', $scope);
                $note->set([
                    'relatedType' => $entity->getEntityType(),
                    'relatedId'   => $entity->id
                ]);
                $this->getEntityManager()->saveEntity($note);
            }
        }
    }

    protected function handleRelationEntity(OrmEntity $entity, string $type): void
    {
        if (!isset($this->relationEntityData[$entity->getEntityType()])) {
            $this->relationEntityData[$entity->getEntityType()] = [];
            if ($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'type']) === 'Relation') {
                $relationFields = $this->getEntityManager()->getRepository($entity->getEntityType())->getRelationFields();

                $this->relationEntityData[$entity->getEntityType()]['field1'] = $relationFields[0] . 'Id';
                $this->relationEntityData[$entity->getEntityType()]['entity1'] = $this->getMetadata()
                    ->get(['entityDefs', $entity->getEntityType(), 'links', $relationFields[0], 'entity']);

                $this->relationEntityData[$entity->getEntityType()]['field2'] = $relationFields[1] . 'Id';
                $this->relationEntityData[$entity->getEntityType()]['entity2'] = $this->getMetadata()
                    ->get(['entityDefs', $entity->getEntityType(), 'links', $relationFields[1], 'entity']);
            }
        }

        if (empty($this->relationEntityData[$entity->getEntityType()])) {
            return;
        }

        $note = $this->getEntityManager()->getEntity('Note');
        $note->set([
            'type'       => $type,
            'parentId'   => $entity->get($this->relationEntityData[$entity->getEntityType()]['field1']),
            'parentType' => $this->relationEntityData[$entity->getEntityType()]['entity1'],
            'data'       => [
                'relatedId'   => $entity->get($this->relationEntityData[$entity->getEntityType()]['field2']),
                'relatedType' => $this->relationEntityData[$entity->getEntityType()]['entity2']
            ]
        ]);
        $this->getEntityManager()->saveEntity($note);

        $note = $this->getEntityManager()->getEntity('Note');
        $note->set([
            'type'       => $type,
            'parentId'   => $entity->get($this->relationEntityData[$entity->getEntityType()]['field2']),
            'parentType' => $this->relationEntityData[$entity->getEntityType()]['entity2'],
            'data'       => [
                'relatedId'   => $entity->get($this->relationEntityData[$entity->getEntityType()]['field1']),
                'relatedType' => $this->relationEntityData[$entity->getEntityType()]['entity1']
            ]
        ]);
        $this->getEntityManager()->saveEntity($note);
    }

    protected function isFollowCreatedEntities(): bool
    {
        if ($this->followCreatedEntities === null) {
            if ($this->getUser()->isSystem()) {
                $this->followCreatedEntities = false;
            } else {
                $this->followCreatedEntities = !empty($this->getPreferences()) && !empty($this->getPreferences()->get('followCreatedEntities'));
            }
        }

        return $this->followCreatedEntities;
    }

    protected function getStreamService(): StreamService
    {
        if ($this->streamService === null) {
            $this->streamService = $this->getContainer()->get('serviceFactory')->create('Stream');
        }

        return $this->streamService;
    }

    protected function getFieldManager(): FieldManager
    {
        return $this->getContainer()->get('fieldManager');
    }

    private function getContainer(): Container
    {
        return $this->container;
    }

    private function getUser(): User
    {
        return $this->getContainer()->get('user');
    }

    private function getPreferences(): ?Preferences
    {
        return $this->getContainer()->get('Preferences');
    }

    private function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    private function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
