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

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Linking and unlinking the records of the relations.
 */
trait LinkableTrait
{
    public function linkEntity($id, $link, $foreignId)
    {
        if (in_array($link, $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit'))) {
            throw new Forbidden();
        }

        /**
         * Delegate to Update if ManyToOne or OneToOne relation
         */
        if ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'type']) === 'belongsTo') {
            $data                = new \stdClass();
            $data->{"{$link}Id"} = $foreignId;
            try {
                $this->updateEntity($id, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        /**
         * Delegate to Update if OneToMany relation
         */
        if (!empty($linkData = $this->getOneToManyRelationData($link))) {
            $this->handleLinkOneToManyRelation($id, $link, $foreignId, $linkData);
            return true;
        }

        $event = $this
            ->dispatchEvent('beforeLinkEntity', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'foreignId' => $foreignId]));

        $id        = $event->getArgument('id');
        $link      = $event->getArgument('link');
        $foreignId = $event->getArgument('foreignId');

        if (empty($id) || empty($link) || empty($foreignId)) {
            throw new BadRequest;
        }

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $foreignEntityType = $entity->getRelationParam($link, 'entity');
        if (!$foreignEntityType) {
            throw new Error("Entity '{$this->entityType}' has not relation '{$link}'.");
        }

        $foreignEntity = $this->getEntityManager()->getEntity($foreignEntityType, $foreignId);
        if (!$foreignEntity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($foreignEntity, 'read')) {
            throw new Forbidden();
        }

        $this->validateArchivedRelation($entity, $foreignEntity);

        $this->getRepository()->relate($entity, $link, $foreignEntity, null, [
            "pseudoTransactionId"      => $this->getPseudoTransactionId(),
            'pseudoTransactionManager' => $this->getPseudoTransactionManager()
        ]);

        return $this
            ->dispatchEvent('afterLinkEntity', new Event(['id' => $id, 'service' => $this, 'entity' => $entity, 'link' => $link, 'foreignEntity' => $foreignEntity, 'result' => true]))
            ->getArgument('result');
    }

    public function duplicateAndLinkEntity($id, $link, $foreignId): bool
    {
        // Link entity with duplicate only support oneToMany Relation

        if (!empty($linkData = $this->getOneToManyRelationData($link))) {
            $this->handleLinkOneToManyRelation($id, $link, $foreignId, $linkData, true);
            return true;
        }

        return false;
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        if (in_array($link, $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit'))) {
            throw new Forbidden();
        }

        /**
         * Delegate to Update if ManyToOne or OneToOne relation
         */
        if ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'type']) === 'belongsTo') {
            $data                = new \stdClass();
            $data->{"{$link}Id"} = null;
            try {
                $this->updateEntity($id, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        /**
         * Delegate to Update if OneToMany relation
         */
        if (!empty($linkData = $this->getOneToManyRelationData($link))) {
            $data                               = new \stdClass();
            $data->{"{$linkData['foreign']}Id"} = null;
            try {
                $this->getServiceFactory()->create($linkData['entity'])->updateEntity($foreignId, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        $event = $this
            ->dispatchEvent('beforeUnlinkEntity', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'foreignId' => $foreignId]));

        $id        = $event->getArgument('id');
        $link      = $event->getArgument('link');
        $foreignId = $event->getArgument('foreignId');

        if (empty($id) || empty($link) || empty($foreignId)) {
            throw new BadRequest;
        }

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $foreignEntityType = $entity->getRelationParam($link, 'entity');
        if (!$foreignEntityType) {
            throw new Error("Entity '{$this->entityType}' has not relation '{$link}'.");
        }

        $foreignEntity = $this->getEntityManager()->getEntity($foreignEntityType, $foreignId);
        if (!$foreignEntity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($foreignEntity, 'read')) {
            throw new Forbidden();
        }

        $this->validateArchivedRelation($entity, $foreignEntity);

        $result = $this->getRepository()->unrelate($entity, $link, $foreignEntity, [
            "pseudoTransactionId"      => $this->getPseudoTransactionId(),
            'pseudoTransactionManager' => $this->getPseudoTransactionManager()
        ]);

        return $this
            ->dispatchEvent('afterUnlinkEntity', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'foreignEntity' => $foreignEntity, 'result' => $result]))
            ->getArgument('result');
    }

    protected function getOneToManyRelationData(string $link): ?array
    {
        $linkData = $this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link], []);
        if (
            array_key_exists('type', $linkData)
            && $linkData['type'] === 'hasMany'
            && array_key_exists('entity', $linkData)
            && array_key_exists('foreign', $linkData)
            && $this->getMetadata()->get(['entityDefs', $linkData['entity'], 'links', $linkData['foreign'], 'type']) === 'belongsTo'
        ) {
            return $linkData;
        }

        return null;
    }

    public function linkEntityMass($id, $link, $where, $selectData = null)
    {
        $massLinkCallback = function ($foreignIds) use ($id, $link) {
            $maxMassLinkCount = $this->getConfig()->get('maxMassLinkCount', 20);
            $key              = $this->getCreateLinkDataKey($id, $link);
            $errorData        = $this->getMemoryStorage()->get($key) ?? [];

            foreach ($foreignIds as $k => $foreignId) {
                if ($k < $maxMassLinkCount) {
                    try {
                        $this->linkEntity($id, $link, $foreignId);
                    } catch (NotUnique $e) {
                        // ignore
                    } catch (\Throwable $e) {
                        $errorData['entity']               = $this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'entity']);
                        $errorData['messages'][$foreignId] = $e->getMessage();
                        $errorData['unableToCreateIds'][]  = $foreignId;
                    }
                } else {
                    $this->getPseudoTransactionManager()->pushLinkEntityJob($this->entityType, $id, $link, $foreignId, null);
                }
            }

            if (!empty($errorData)) {
                $this->getMemoryStorage()->set($key, $errorData);
            }
        };
        return $this->processDuplicateOrAndLinkEntityMass($id, $link, $where, $massLinkCallback, $selectData);
    }

    public function duplicateAndLinkEntityMass($id, $link, $where, $selectData = null)
    {
        $massLinkCallback = function ($foreignIds) use ($id, $link) {
            $maxMassLinkCount = $this->getConfig()->get('maxMassLinkCount', 20);
            foreach ($foreignIds as $k => $foreignId) {
                if ($k < $maxMassLinkCount) {
                    $this->duplicateAndLinkEntity($id, $link, $foreignId);
                } else {
                    $this->getPseudoTransactionManager()->pushLinkEntityJob($this->entityType, $id, $link, $foreignId, null, true);
                }
            }
        };
        return $this->processDuplicateOrAndLinkEntityMass($id, $link, $where, $massLinkCallback, $selectData);
    }

    public function unlinkAll(string $id, string $link): bool
    {
        $event = $this->dispatchEvent('beforeUnlinkAll', new Event(['id' => $id, 'service' => $this, 'link' => $link]));

        $id   = $event->getArgument('id');
        $link = $event->getArgument('link');

        if (empty($id) || empty($link)) {
            throw new BadRequest("'id' and 'link' is required parameters.");
        }

        if (empty($entity = $this->getRepository()->get($id))) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        if (empty($foreignEntityType = $entity->getRelationParam($link, 'entity'))) {
            throw new Error();
        }

        if (!$this->getAcl()->check($foreignEntityType, 'read')) {
            throw new Forbidden();
        }

        /**
         * A one-to-one relation has no link multiple id list. Both its sides keep the id of the single
         * related record in the `<link>Id` attribute: a real column on the `belongsTo` side and a foreign
         * attribute taken from the joined table on the `hasOne` side.
         */
        if (in_array($entity->getRelationParam($link, 'type'), [IEntity::BELONGS_TO, IEntity::HAS_ONE])) {
            $foreignIds = array_values(array_filter([$entity->get("{$link}Id")]));
        } else {
            $foreignIds = $entity->getLinkMultipleIdList($link) ?? [];
        }

        $maxMassUnlinkCount = $this->getConfig()->get('maxMassUnlinkCount', 20);

        foreach ($foreignIds as $k => $foreignId) {
            if ($k < $maxMassUnlinkCount) {
                $this->unlinkEntity($id, $link, $foreignId);
            } else {
                $this->getPseudoTransactionManager()->pushUnLinkEntityJob($this->entityType, $id, $link, $foreignId);
            }
        }

        return $this->dispatchEvent('afterUnlinkAll', new Event(['entity' => $entity, 'service' => $this, 'link' => $link, 'result' => true]))->getArgument('result');
    }

    protected function getCreateLinkDataKey(string $id, string $link): string
    {
        return "createLinkData{$this->entityType}$id$link";
    }

    public function handleLinkEntitiesErrors(string $id, string $link, bool $shouldDuplicateForeign): void
    {
        if (!empty($this->getMemoryStorage()->get('importJobId'))) {
            return;
        }

        $data = $this->getMemoryStorage()->get($this->getCreateLinkDataKey($id, $link));

        if (empty($data['entity']) || empty($data['unableToCreateIds'])) {
            return;
        }

        $message = $this->getInjection('language')->translate($shouldDuplicateForeign ? 'someCouldNotDuplicatedAndLinked' : 'someCouldNotLinked', 'exceptions');

        try {
            $conn    = $this->getEntityManager()->getConnection();
            $results = $conn->createQueryBuilder()
                ->select('c.id, c.name')
                ->from($conn->quoteIdentifier(Util::toUnderScore($data['entity'])), 'c')
                ->where('c.id in (:ids)')
                ->andWhere('c.deleted = :false')
                ->setParameter('ids', $data['unableToCreateIds'], Mapper::getParameterType($data['unableToCreateIds']))
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setMaxResults(20)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {

        }

        if (!empty($results)) {
            $message .= ':<ul>';
            foreach ($results as $result) {
                $url     = $this->getConfig()->get('siteUrl') . '#' . $data['entity'] . '/view/' . $result['id'];
                $message .= '<li><a href="' . $url . '" target="_blank">' . $result['name'] . ': ' . (!empty($data['messages'][$result['id']]) ? $data['messages'][$result['id']] : '') . '</a></li>';
            }
            $message .= '</ul>';
        }

        throw new BadRequest($message);
    }

    protected function handleLinkOneToManyRelation(string $id, string $link, string $foreignId, array $linkData, bool $duplicateForeign = false): void
    {
        if (empty($linkData)) {
            return;
        }

        $data                               = new \stdClass();
        $data->{"{$linkData['foreign']}Id"} = $id;
        $key                                = $this->getCreateLinkDataKey($id, $link);
        $createLinkData                     = $this->getMemoryStorage()->get($key) ?? [];
        try {
            $foreignService = $this->getServiceFactory()->create($linkData['entity']);
            if ($duplicateForeign) {
                $data->_duplicatingEntityId = $foreignId;
                foreach ($foreignService->getDuplicateAttributes($foreignId) as $field => $value) {
                    if (property_exists($data, $field)) {
                        continue 1;
                    }
                    $data->$field = $value;
                }
                $foreignService->createEntity($data);
            } else {
                $foreignService->updateEntity($foreignId, $data);
            }
        } catch (NotModified $e) {
            // ignore
        } catch (BadRequest $e) {
            $createLinkData['entity']               = $linkData['entity'];
            $createLinkData['messages'][$foreignId] = $e->getMessage();
            $createLinkData['unableToCreateIds'][]  = $foreignId;
            $this->getMemoryStorage()->set($key, $createLinkData);
        }
    }

    protected function processDuplicateOrAndLinkEntityMass($id, $link, $where, \Closure $linkOrdDuplicateCallback, $selectData = null)
    {
        $event = $this->dispatchEvent('beforeLinkEntityMass', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'where' => $where]));

        $id    = $event->getArgument('id');
        $link  = $event->getArgument('link');
        $where = $event->getArgument('where');

        if (empty($id) || empty($link)) {
            throw new BadRequest("'id' and 'link' is required parameters.");
        }

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $foreignEntityType = $entity->getRelationParam($link, 'entity');

        if (empty($foreignEntityType)) {
            throw new Error();
        }

        if (!$this->getAcl()->check($foreignEntityType, 'read')) {
            throw new Forbidden();
        }

        if (empty($where) || !is_array($where)) {
            $where = [];
        }

        $selectParams = $this->getRecordService($foreignEntityType)->getSelectParams(['where' => $where]);
        $this->getEntityManager()->getRepository($foreignEntityType)->handleSelectParams($selectParams);

        $collection = $this->getEntityManager()->getRepository($foreignEntityType)->find(array_merge($selectParams, ['select' => ['id']]));

        $foreignIds = array_column($collection->toArray(), 'id');

        $linkOrdDuplicateCallback($foreignIds);

        $result = count($foreignIds) <= $this->getConfig()->get('maxMassLinkCount', 20);

        return $this->dispatchEvent('afterLinkEntityMass', new Event(['entity' => $entity, 'service' => $this, 'link' => $link, 'result' => $result]))->getArgument('result');
    }

    /**
     * Relates the records to the foreign records by the link, in bulk.
     */
    public function addRelation(array $ids, array $foreignIds, string $link, ?array $relationData = null): array
    {
        $entityType = $this->entityName;
        [$ids, $entityType] = $this->resolveSupertypeLink($ids, $entityType, $link);

        if (!empty($this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'isMainAssociateRelation']))) {
            return $this->addAssociateRecords($ids, $foreignIds, $entityType, $relationData);
        }
        // prepare service
        $service = $this->getRecordService($entityType);
        $methodName = 'massRelate' . ucfirst($link);

        // if method exists
        if (method_exists($service, $methodName)) {
            return $service->$methodName($ids, $foreignIds);
        }

        // prepare repository
        $repository = $this->getEntityManager()->getRepository($entityType);

        // find entities
        $entities = $repository->where(['id' => $ids])->find();

        /** @var string $foreignEntityType */
        $foreignEntityType = $this->getForeignEntityType($entityType, $link);

        // find foreign entities
        $foreignEntities = $this->getEntityManager()->getRepository($foreignEntityType)
            ->where(['id' => $foreignIds])
            ->find();

        $related = 0;
        $notRelated = [];
        if ($entities->count() > 0 && $foreignEntities->count() > 0) {
            foreach ($entities as $entity) {
                foreach ($foreignEntities as $foreignEntity) {
                    $related++;
                    try {
                        $repository->relate($entity, $link, $foreignEntity);
                    } catch (UniqueConstraintViolationException $e) {
                    } catch (NotUnique $e) {
                    } catch (BadRequest $e) {
                        $related--;
                        $notRelated[] = [
                            'id'          => $entity->get('id'),
                            'name'        => $entity->get('name'),
                            'foreignId'   => $foreignEntity->get('id'),
                            'foreignName' => $foreignEntity->get('name'),
                            'message'     => utf8_encode($e->getMessage())
                        ];
                    }
                }
            }
        }

        return ['message' => $this->createRelationMessage($related, $notRelated, $entityType, $foreignEntityType)];
    }

    /**
     * Unrelates the records from the foreign records by the link, in bulk.
     */
    public function removeRelation(array $ids, array $foreignIds, string $link, ?array $relationData = null): array
    {
        $entityType = $this->entityName;
        [$ids, $entityType] = $this->resolveSupertypeLink($ids, $entityType, $link);

        if (!empty($this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'isMainAssociateRelation']))) {
            return $this->removeAssociateRecords($ids, $foreignIds, $entityType, $relationData);
        }

        // prepare service
        $service = $this->getRecordService($entityType);
        $methodName = 'massUnrelate' . ucfirst($link);

        // if method exists
        if (method_exists($service, $methodName)) {
            return $service->$methodName($ids, $foreignIds);
        }

        // prepare repository
        $repository = $this->getEntityManager()->getRepository($entityType);

        // find entities
        $entities = $repository->where(['id' => $ids])->find();

        /** @var string $foreignEntityType */
        $foreignEntityType = $this->getForeignEntityType($entityType, $link);

        // find foreign entities
        $foreignEntities = $this->getEntityManager()->getRepository($foreignEntityType)
            ->where(['id' => $foreignIds])
            ->find();

        $unRelated = 0;
        $notUnRelated = [];
        if ($entities->count() > 0 && $foreignEntities->count() > 0) {
            foreach ($entities as $entity) {
                foreach ($foreignEntities as $foreignEntity) {
                    $unRelated++;
                    try {
                        $repository->unrelate($entity, $link, $foreignEntity);
                    } catch (BadRequest $e) {
                        $unRelated--;
                        $notUnRelated[] = [
                            'id'          => $entity->get('id'),
                            'name'        => $entity->get('name'),
                            'foreignId'   => $foreignEntity->get('id'),
                            'foreignName' => $foreignEntity->get('name'),
                            'message'     => utf8_encode($e->getMessage())
                        ];
                    }
                }
            }
        }

        return ['message' => $this->createRelationMessage($unRelated, $notUnRelated, $entityType, $foreignEntityType, false)];
    }

    /**
     * The relations of the supertype shown by a subtype are served by the supertype records.
     */
    protected function resolveSupertypeLink(array $ids, string $entityType, string $link): array
    {
        if (
            empty($this->getMetadata()->get(['scopes', $entityType, 'supertypeId']))
            || empty($this->getMetadata()->get(['entityDefs', $entityType, 'fields', $link, 'supertypeField']))
        ) {
            return [$ids, $entityType];
        }

        $supertypeIds = [];
        if (!empty($ids)) {
            $records = $this->getEntityManager()->getRepository($entityType)
                ->select(['id', 'supertypeId'])
                ->where(['id' => $ids])
                ->find();

            foreach ($records as $record) {
                $supertypeIds[] = $record->get('supertypeId');
            }
        }

        return [$supertypeIds, $this->getMetadata()->get(['scopes', $entityType, 'supertypeId'])];
    }

    protected function addAssociateRecords(array $ids, array $foreignIds, string $entityType, ?array $relationData = null): array
    {
        if (empty($this->getMetadata()->get(['scopes', $entityType, 'hasAssociate']))) {
            throw new BadRequest("Associate is not set on " . $entityType);
        }

        // input data validation
        if (empty($relationData['associationId'])) {
            throw new BadRequest($this->getInjection('language')->translate('wrongInputData', 'exceptions'));
        }

        /** @var Entity $association */
        $association = $this->getEntityManager()->getEntity("Association", $relationData['associationId']);
        if (empty($association)) {
            throw new BadRequest($this->getInjection('language')->translate('noSuchAssociation', 'exceptions'));
        }

        /**
         * Collect entities for saving
         */
        $toSave = [];
        foreach ($ids as $mainRecordId) {
            foreach ($foreignIds as $relatedRecordId) {
                $attachment = new \stdClass();
                $attachment->associationId = $relationData['associationId'];
                $attachment->associatingItemId = $mainRecordId;
                $attachment->associatedItemId = $relatedRecordId;
                if (!empty($reverseAssociationId = $association->get('reverseAssociationId'))) {
                    $attachment->reverseAssociationId = $reverseAssociationId;
                }

                $toSave[] = $attachment;
            }
        }

        $associatedRecordService = $this->getRecordService("Associated$entityType");

        $error = [];
        foreach ($toSave as $attachment) {
            try {
                $associatedRecordService->createEntity($attachment);
            } catch (\Exception $e) {
                $error[] = [
                    'id'          => $attachment->associatingItemId,
                    'name'        => $this->getEntityManager()->getEntity($entityType, $attachment->associatingItemId)->get('name'),
                    'foreignId'   => $attachment->associatedItemId,
                    'foreignName' => $this->getEntityManager()->getEntity($entityType, $attachment->associatedItemId)->get('name'),
                    'message'     => utf8_encode($e->getMessage())
                ];
            }
        }

        return ['message' => $this->createRelationMessage(count($toSave) - count($error), $error, $entityType, $entityType)];
    }

    protected function removeAssociateRecords(array $ids, array $foreignIds, string $entityType, ?array $relationData = null): array
    {
        if (empty($this->getMetadata()->get(['scopes', $entityType, 'hasAssociate']))) {
            throw new BadRequest("Associate is not set on " . $entityType);
        }

        $where = [
            "associatingItemId" => $ids,
            "associatedItemId"  => $foreignIds
        ];

        if (!empty($relationData['associationId'])) {
            $where['associationId'] = $relationData['associationId'];
        }

        $associatedRecords = $this
            ->getEntityManager()
            ->getRepository("Associated$entityType")
            ->where($where)
            ->find();

        $success = 0;
        $error = [];
        foreach ($associatedRecords as $associatedRecord) {
            try {
                $this->getEntityManager()->removeEntity($associatedRecord);
                $success++;
            } catch (\Exception $e) {
                $error[] = [
                    'id'          => $associatedRecord->get("associatingItemId"),
                    'name'        => $associatedRecord->get("associatingItem")->get('name'),
                    'foreignId'   => $associatedRecord->get("associatedItemId"),
                    'foreignName' => $associatedRecord->get("associatedItem")->get('name'),
                    'message'     => utf8_encode($e->getMessage())
                ];
            }
        }

        return ['message' => $this->createRelationMessage($success, $error, $entityType, $entityType, false)];
    }

    protected function createRelationMessage(int $success, array $errors, string $entityType, string $foreignEntityType, bool $relate = true): string
    {
        $message = "<button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>";
        if (!empty($success)) {
            $plural = $success > 1 ? 'Plural' : '';
            $successMessage = $relate ? $this->getInjection('language')->translate('relationsAdded' . $plural, 'messages') : $this->getInjection('language')->translate('relationsRemoved' . $plural, 'messages');
            $message .= "<span>" . sprintf($successMessage, $success) . "</span><br>";
        }
        if (!empty($errors)) {
            $plural = count($errors) > 1 ? 'Plural' : '';
            $errorMessage = $relate ? $this->getInjection('language')->translate('relationsDidNotAdded' . $plural, 'messages') : $this->getInjection('language')->translate('relationsDidNotRemoved' . $plural, 'messages');
            $message .= "<span style=\"color: red\">" . sprintf($errorMessage, count($errors)) . "</span><br>";
            foreach ($errors as $item) {
                $message .= "<span style=\"margin-left: 10px; color: #000\"><a target=\"_blank\" href=\"#{$entityType}/view/{$item['id']}\">{$item['name']}</a> &#8594; <a target=\"_blank\" href=\"#{$foreignEntityType}/view/{$item['foreignId']}\">{$item['foreignName']}</a>: {$item['message']}</span><br>";
            }
        } else {
            if ($success === 0) {
                $message .= "<span>" . $this->getInjection('language')->translate('Done') . "</span>";
            }
        }

        return $message;
    }

    protected function getForeignEntityType(string $entityType, string $link): string
    {
        $foreignEntityType = $this->getEntityManager()->getEntity($entityType)->getRelationParam($link, 'entity');

        if (empty($foreignEntityType)) {
            throw new BadRequest("No such relation found.");
        }

        return $foreignEntityType;
    }
}
