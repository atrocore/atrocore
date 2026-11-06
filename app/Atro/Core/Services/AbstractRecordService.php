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

namespace Atro\Core\Services;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\ConditionChecker;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Services\Interfaces\DeletableInterface;
use Atro\Core\Services\Interfaces\EditableInterface;
use Atro\Core\Services\Interfaces\MassActionsInterface;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\RequestHeaders;
use Atro\Core\PseudoTransactionManager;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\UniqueIndexDescriber;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;
use Atro\Core\Utils\NameField;

/**
 * Base of the services of the entities: reading the records, preparing them for the output and their meta.
 * The other operations are added by the traits of Atro\Core\Services\Traits, the service of each entity type
 * (Atro\Core\Templates\Services) uses the ones its type supports.
 */
abstract class AbstractRecordService extends AbstractService
{
    protected $dependencies = array(
        'entityManager',
        'user',
        'metadata',
        'acl',
        'aclManager',
        'config',
        'serviceFactory',
        'fileManager',
        'selectManagerFactory',
        'injectableFactory',
        'fieldManagerUtil',
        'eventManager',
        'language',
        'pseudoTransactionManager',
        'memoryStorage',
        'condition',
        AttributeFieldConverter::class,
        UniqueIndexDescriber::class
    );

    protected array $cachedOptionData = [];

    protected $entityName;

    protected $entityType;

    protected $internalAttributeList = [];

    protected $linkSelectParams = [];

    protected $listCountQueryDisabled = false;

    protected $maxSelectTextAttributeLength = null;

    protected $maxSelectTextAttributeLengthDisabled = false;

    protected $skipSelectTextAttributes = false;

    protected $selectAttributeList = null;

    protected $mandatorySelectAttributeList = [];

    protected $forceSelectAllAttributes = false;

    protected string $pseudoTransactionId = '';

    protected array|null $collectionUsers = null;

    const MAX_SELECT_TEXT_ATTRIBUTE_LENGTH = 5000;

    public function __construct()
    {
        parent::__construct();
        if (empty($this->entityType)) {
            $name = get_class($this);
            if (preg_match('@\\\\([\w]+)$@', $name, $matches)) {
                $name = $matches[1];
            }
            $this->entityType = Util::normilizeScopeName($name);
        }
        $this->entityName = $this->entityType;
    }

    public function setEntityType($entityType)
    {
        $this->entityType = $entityType;
        $this->entityName = $entityType;
    }

    public function getEntityType()
    {
        return $this->entityType;
    }

    public function setPseudoTransactionId(string $id): void
    {
        $this->pseudoTransactionId = $id;
    }

    public function getPseudoTransactionId(): string
    {
        return $this->pseudoTransactionId;
    }

    public function isPseudoTransaction(): bool
    {
        return !empty($this->getPseudoTransactionId());
    }

    protected function getServiceFactory()
    {
        return $this->injections['serviceFactory'];
    }

    protected function getSelectManagerFactory()
    {
        return $this->injections['selectManagerFactory'];
    }

    protected function getAcl()
    {
        return $this->getInjection('acl');
    }

    protected function getAclManager()
    {
        return $this->getInjection('aclManager');
    }

    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }

    /**
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getFieldManagerUtil()
    {
        return $this->getInjection('fieldManagerUtil');
    }

    protected function getRepository()
    {
        return $this->getEntityManager()->getRepository($this->entityType);
    }

    protected function getRecordService($name)
    {
        return $this->getServiceFactory()->create($name);
    }

    public function readEntity(string $id, ?string $withRelationships = null): ?IEntity
    {
        $id = $this
            ->dispatchEvent('beforeReadEntity', new Event(['id' => $id, 'service' => $this]))
            ->getArgument('id');

        if (empty($id)) {
            throw new Error();
        }
        $entity = $this->getEntity($id, $withRelationships);

        return $this
            ->dispatchEvent('afterReadEntity', new Event(['id' => $id, 'entity' => $entity, 'service' => $this]))
            ->getArgument('entity');
    }

    public function getEntity(string $id, ?string $withRelationships = null): ?IEntity
    {
        $id = $this
            ->dispatchEvent('beforeGetEntity', new Event(['id' => $id, 'service' => $this]))
            ->getArgument('id');

        if (!empty($language = $this->getHeaderLanguage())) {
            if (in_array($language, $this->getAcl()->getForbiddenLanguageList('read'))) {
                throw new Forbidden();
            }
        }

        $this->getPseudoTransactionManager()->runForEntity($this->getEntityType(), $id);

        $entity = $this->prepareEntityById($id, true, true, $withRelationships);

        return $this
            ->dispatchEvent('afterGetEntity', new Event(['id' => $id, 'entity' => $entity, 'service' => $this]))
            ->getArgument('entity');
    }

    public function prepareEntityById(string $id, bool $checkAcl = false, bool $useCache = false, ?string $withRelationships = null): ?Entity
    {
        if (!$useCache) {
            $this->clearCachedEntity($id);
        }
        $entity = $this->getRepository()->get($id);
        if (empty($entity)) {
            return null;
        }
        $this->getInjection(AttributeFieldConverter::class)->putAttributesToEntity($entity);
        $this->loadAdditionalFields($entity, $withRelationships !== null);
        if ($withRelationships !== null) {
            $this->loadWithRelationships($entity, $withRelationships);
        }
        $entity->loadLinkMultipleField('teams');
        $this->calculateScriptFields($entity);

        if ($checkAcl && !$this->getAcl()->check($entity, 'read')) {
            throw new Forbidden();
        }

        if (RequestHeaders::isMetaRequested()) {
            $this->putMeta($entity);
            if (RequestHeaders::isMetaGroupRequested('availableActions')) {
                $this->putAclMeta($entity);
            }
        }

        $this->prepareEntityForOutput($entity);

        return $entity;
    }

    protected function getScriptFieldsList(Entity $entity): array
    {
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'fields']) as $field => $defs) {
            if (!empty($defs['type']) && $defs['type'] === 'script') {
                $result[] = $field;
            }
        }

        return $result;
    }

    /**
     * The record is read from the database, not from the cache of the request.
     */
    protected function clearCachedEntity(string $id): void
    {
        $this->getMemoryStorage()->delete($this->getRepository()->getCacheKey($id));
    }

    protected function calculateScriptFields(Entity $entity): void
    {
        if (!$this->hasScriptFields($entity)) {
            return;
        }

        $entity->_realtimeDisabled = true;
        $this->getRepository()->calculateScriptFields($entity);
    }

    protected function hasScriptFields(Entity $entity): bool
    {
        foreach ($entity->entityDefs['fields'] ?? [] as $fieldDefs) {
            if (($fieldDefs['type'] ?? null) === 'script' && !empty($fieldDefs['script'])) {
                return true;
            }
        }

        return false;
    }

    protected function loadLinkMultipleFields(Entity $entity)
    {
        $fieldDefs = $this->getMetadata()->get('entityDefs.' . $entity->getEntityName() . '.fields', array());

        foreach ($fieldDefs as $field => $defs) {
            if (isset($defs['type']) && $defs['type'] === 'linkMultiple' && empty($defs['noLoad'])) {
                $columns = null;
                if (!empty($defs['columns'])) {
                    $columns = $defs['columns'];
                }
                $entity->loadLinkMultipleField($field, $columns);
            }
        }
    }

    protected function loadWithRelationships(Entity $entity, string $withRelationships): void
    {
        $fields    = array_filter(array_map('trim', explode(',', $withRelationships)));
        $fieldDefs = $this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'fields'], []);
        foreach ($fields as $field) {
            $defs = $fieldDefs[$field] ?? [];
            if (($defs['type'] ?? '') !== 'linkMultiple') continue;
            if ($entity->has($field . 'Ids')) continue;
            $entity->loadLinkMultipleField($field, $defs['columns'] ?? null);
        }
    }

    protected function batchLoadLinkMultipleFieldsForList(EntityCollection $collection, array $selectAttributeList): void
    {
        if (count($collection) === 0) return;

        $entityIds = [];
        $entityMap = [];
        foreach ($collection as $entity) {
            $id             = $entity->get('id');
            $entityIds[]    = $id;
            $entityMap[$id] = $entity;
        }

        foreach ($selectAttributeList as $attribute) {
            if (!str_ends_with($attribute, 'Ids')) continue;
            $field = substr($attribute, 0, -3);

            $sampleEntity = reset($entityMap);
            if (!$sampleEntity->hasRelation($field) || !$sampleEntity->hasAttribute($field . 'Ids')) continue;

            $this->getRepository()->batchLoadLinkMultipleField($entityMap, $field);
        }
    }

    protected function loadLinkMultipleFieldsForList(Entity $entity, $selectAttributeList)
    {
        foreach ($selectAttributeList as $attribute) {
            if ($entity->getAttributeParam($attribute, 'isLinkMultipleIdList') && !$entity->has($attribute)) {
                $field = $entity->getAttributeParam($attribute, 'relation');
                if ($field) {
                    $entity->loadLinkMultipleField($field);
                }
            }

            if ($entity->getAttributeParam($attribute, 'isLinkEntity') && !$entity->has($attribute)) {
                $linkedEntities = $this->findLinkedEntities($entity->get('id'), $attribute, []);
                $entity->set($attribute, null);
                if ($linkedEntities['total'] > 0) {
                    $linkedEntitiesList = array_key_exists('collection', $linkedEntities) ? $linkedEntities['collection']->toArray() : $linkedEntities['list'];
                    $entity->set($attribute, $linkedEntitiesList[0]);
                }
            }

            if ($entity->getAttributeParam($attribute, 'isLinkMultipleCollection') && !$entity->has($attribute)) {
                $linkDefs = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $attribute]);
                if (!empty($linkDefs['entity']) && $this->getMetadata()->get(['scopes', $linkDefs['entity'], 'type']) === 'Relationship') {
                    $linkedEntities = $this
                        ->getServiceFactory()
                        ->create($linkDefs['entity'])
                        ->findEntities([
                            'where' => [
                                [
                                    'type'      => 'equals',
                                    'attribute' => $linkDefs['foreign'] . 'Id',
                                    'value'     => $entity->get('id'),
                                ]
                            ]
                        ]);
                } else {
                    $linkedEntities = $this->findLinkedEntities($entity->get('id'), $attribute, []);
                }
                $entity->set($attribute, []);
                if ($linkedEntities['total'] > 0) {
                    $linkedEntitiesList = array_key_exists('collection', $linkedEntities) ? $linkedEntities['collection']->toArray() : $linkedEntities['list'];
                    $entity->set($attribute, $linkedEntitiesList);
                }
            }
        }
    }

    protected function loadLinkFields(Entity $entity)
    {
        $fieldDefs = $this->getMetadata()->get('entityDefs.' . $entity->getEntityName() . '.fields', array());
        $linkDefs  = $this->getMetadata()->get('entityDefs.' . $entity->getEntityName() . '.links', array());

        foreach ($fieldDefs as $field => $defs) {
            if (isset($defs['type']) && $defs['type'] === 'link') {
                if (!empty($defs['noLoad'])) continue;
                if (empty($linkDefs[$field])) continue;
                if (empty($linkDefs[$field]['type'])) continue;
                if ($linkDefs[$field]['type'] === 'hasOne') {
                    $entity->loadLinkField($field);
                }

                if ((!empty($scope = $linkDefs[$field]['entity']) && $this->getMetadata()->get(['scopes', $scope, 'type']) === 'ReferenceData') &&
                    !empty($entity->get("{$field}Id"))) {
                    $value = $this->getEntityManager()->getEntity($scope, $entity->get("{$field}Id"));
                    if (!empty($value)) {
                        $entity->set("{$field}Name", $value->get('name'));
                    }
                }
            }
        }
    }

    protected function loadParentNameFields(Entity $entity)
    {
        $fieldDefs = $this->getMetadata()->get('entityDefs.' . $entity->getEntityType() . '.fields', array());
        foreach ($fieldDefs as $field => $defs) {
            if (isset($defs['type']) && $defs['type'] == 'linkParent') {
                $parentId   = $entity->get($field . 'Id');
                $parentType = $entity->get($field . 'Type');
                $entity->loadParentNameField($field);
            }
        }
    }

    protected function loadNotJoinedLinkFields(Entity $entity)
    {
        $linkDefs = $this->getMetadata()->get('entityDefs.' . $entity->getEntityType() . '.links', array());
        foreach ($linkDefs as $link => $defs) {
            if (isset($defs['type']) && $defs['type'] == 'belongsTo') {
                if (!empty($defs['noJoin']) && !empty($defs['entity'])) {
                    $nameField = $link . 'Name';
                    $idField   = $link . 'Id';
                    if ($entity->hasAttribute($nameField) && $entity->hasAttribute($idField)) {
                        $id = $entity->get($idField);
                    }

                    $scope = $defs['entity'];
                    if (!empty($scope) && $foreignEntity = $this->getEntityManager()->getEntity($scope, $id)) {
                        $entity->set($nameField, $foreignEntity->get('name'));
                    }
                }
            }
        }
    }

    public function loadAdditionalFields(Entity $entity, bool $skipLinkMultiple = false)
    {
        $this->loadLinkFields($entity);
        if (!$skipLinkMultiple) {
            $this->loadLinkMultipleFields($entity);
        }
        $this->loadParentNameFields($entity);
        $this->loadNotJoinedLinkFields($entity);
        $this->loadPreview($entity);
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        $this->loadPreviewForCollection($collection);
        $this->loadUsersForCollection($collection);

        $this->dispatchEvent('prepareCollectionForOutput', new Event(['collection' => $collection, 'selectParams' => $selectParams, 'service' => $this]));

        foreach ($collection as $entity) {
            $entity->_fromCollection = true;
        }
    }

    protected function loadUsersForCollection(EntityCollection $collection): void
    {
        if ($this->collectionUsers !== null) {
            return;
        }

        $usersIds = [];
        foreach ($collection as $entity) {
            foreach (['createdById', 'modifiedById'] as $field) {
                if (!empty($entity->get($field))) {
                    $usersIds[$entity->get($field)] = true;
                }
            }
        }

        if (!empty($usersIds)) {
            $collectionUsers = $this->getEntityManager()->getRepository('User')
                ->where(['id' => array_keys($usersIds)])
                ->find();

            foreach ($collectionUsers as $collectionUser) {
                $this->collectionUsers[$collectionUser->id] = $collectionUser;
            }
        }
    }

    public function loadPreviewForCollection(EntityCollection $collection): void
    {
        $this->dispatchEvent('loadPreviewForCollection', new Event(['collection' => $collection, 'service' => $this]));

        if (!empty($this->getMemoryStorage()->get('exportJobId')) || empty($collection[0])) {
            return;
        }

        $fields = [];
        foreach ($this->getMetadata()->get(['entityDefs', $collection->getEntityName(), 'fields'], []) as $field => $data) {
            if (in_array($data['type'], ['asset', 'image', 'file']) && empty($data['relationVirtualField'])) {
                $fields[] = $field;
            }
        }

        if (empty($fields)) {
            return;
        }

        $ids = [];
        foreach ($fields as $field) {
            foreach ($collection as $entity) {
                $id = $entity->get("{$field}Id");
                if (!empty($id)) {
                    $ids[] = $id;
                }
            }
        }

        if (empty($ids)) {
            return;
        }

        foreach ($this->getEntityManager()->getRepository('File')->where(['id' => $ids])->find(["withDeleted" => true]) as $file) {
            $files[$file->get('id')] = [
                'name'      => $file->get('name'),
                'pathsData' => $file->getPathsData(),
            ];
        }

        foreach ($fields as $field) {
            foreach ($collection as $entity) {
                $fileId = $entity->get("{$field}Id");
                if (isset($files[$fileId])) {
                    $entity->set("{$field}Id", $fileId);
                    $entity->set("{$field}Name", $files[$fileId]['name']);
                    $entity->set("{$field}PathsData", $files[$fileId]['pathsData']);
                } else {
                    $entity->set("{$field}Id", null);
                    $entity->set("{$field}Name", null);
                }
            }
        }
    }

    public function loadPreview(Entity $entity): void
    {
        if (!empty($this->getMemoryStorage()->get('importJobId')) || $this->isPseudoTransaction()) {
            return;
        }

        $fields = [];
        foreach ($entity->entityDefs['fields'] ?? $this->getMetadata()->get("entityDefs.{$entity->getEntityType()}.fields") as $field => $data) {
            if (!empty($data['type']) && in_array($data['type'], ['asset', 'image', 'file']) && !empty($entity->get("{$field}Id"))) {
                $fields[$field] = $entity->get("{$field}Id");
            }
        }

        if (empty($fields)) {
            return;
        }

        $files = $this->getEntityManager()->getRepository('File')
            ->where(['id' => array_unique(array_values($fields))])
            ->find();

        foreach (array_keys($fields) as $field) {
            $entity->set("{$field}Id", null);
            $entity->set("{$field}Name", null);
        }

        foreach ($files as $file) {
            foreach ($fields as $field => $fileId) {
                if ($file->id == $fileId) {
                    $entity->set("{$field}Id", $file->get('id'));
                    $entity->set("{$field}Name", $file->get('name'));
                    $entity->set("{$field}PathsData", $file->getPathsData());
                }
            }
        }
    }

    public function loadAdditionalFieldsForList(Entity $entity)
    {
        $this->loadParentNameFields($entity);
    }

    protected function getSelectManager($entityType = null)
    {
        if (!$entityType) {
            $entityType = $this->getEntityType();
        }
        return $this->getSelectManagerFactory()->create($entityType);;
    }

    public function getSelectParams($params)
    {
        return $this->getSelectManager($this->entityType)->getSelectParams($params, true, true);
    }

    public function findEntities($params)
    {
        $params = $this
            ->dispatchEvent('beforeFindEntities', new Event(['params' => $params, 'service' => $this]))
            ->getArgument('params');

        if (!empty($language = $this->getHeaderLanguage())) {
            if (in_array($language, $this->getAcl()->getForbiddenLanguageList('read'))) {
                throw new Forbidden();
            }
        }

        $disableCount = $params['disableCount'] ?? false;
        if (
            $this->listCountQueryDisabled
            ||
            in_array($this->entityType, $this->getConfig()->get('disabledCountQueryEntityList', []))
        ) {
            $disableCount = true;
        }

        $maxSize = 0;
        if ($disableCount) {
            if (!empty($params['maxSize'])) {
                $maxSize           = $params['maxSize'];
                $params['maxSize'] = $params['maxSize'] + 1;
            }
        }

        $selectParams = $this->getSelectParams($params);

        if (!empty($params['allAttributes'])) {
            $selectParams['allAttributes'] = true;
        } elseif (!empty($params['attributesIds'])) {
            $selectParams['attributesIds'] = $params['attributesIds'];
        }

        // a module may pass its own list params to the select
        $selectParams = $this
            ->dispatchEvent('afterPrepareSelectParams', new Event(['params' => $params, 'selectParams' => $selectParams, 'service' => $this]))
            ->getArgument('selectParams');

        if (!empty($params['completeAttrDefs'])) {
            $selectParams['completeAttrDefs'] = true;
        }

        $selectParams['maxTextColumnsLength'] = $this->getMaxSelectTextAttributeLength();

        $selectAttributeList = $this->getSelectAttributeList($params);
        if ($selectAttributeList) {
            $selectParams['select'] = $selectAttributeList;
        } else {
            $selectParams['skipTextColumns'] = $this->isSkipSelectTextAttributes();
        }

        if (!empty($params['totalOnly'])) {
            return ['total' => $this->getRepository()->count($selectParams)];
        }

        $collection = $this->getRepository()->find($selectParams);

        $this->prepareCollectionForOutput($collection, $selectParams);

        if (!empty($selectAttributeList)) {
            $this->batchLoadLinkMultipleFieldsForList($collection, $selectAttributeList);
        }

        foreach ($collection as $e) {
            $this->loadAdditionalFieldsForList($e);
            if (!empty($params['loadAdditionalFields'])) {
                $this->loadAdditionalFields($e);
            }
            if (!empty($selectAttributeList)) {
                $this->loadLinkMultipleFieldsForList($e, $selectAttributeList);

                if (!empty(array_intersect($selectAttributeList, $this->getScriptFieldsList($e)))) {
                    $this->calculateScriptFields($e);
                }
            } elseif (!empty($params['calculateScriptFields'])) {
                $this->calculateScriptFields($e);
            }
            if (empty($params['skipMeta']) && RequestHeaders::isMetaRequested()) {
                $this->putMeta($e);
                if (RequestHeaders::isMetaGroupRequested('availableActions')) {
                    $this->putAclMeta($e);
                }
            }
            $this->prepareEntityForOutput($e);
        }

        $total = 0;
        if (empty($params['collectionOnly'])) {
            if (!$disableCount) {
                $total = $this->getRepository()->count($selectParams);
            } else {
                if ($maxSize && count($collection) > $maxSize) {
                    $total = -1;
                    unset($collection[count($collection) - 1]);
                } else {
                    $total = -2;
                }
            }
        }

        return $this
            ->dispatchEvent('afterFindEntities', new Event(['params' => $params, 'service' => $this, 'result' => ['total' => $total, 'collection' => $collection]]))
            ->getArgument('result');
    }

    public function getMaxSelectTextAttributeLength()
    {
        if (!$this->maxSelectTextAttributeLengthDisabled) {
            if ($this->maxSelectTextAttributeLength) {
                return $this->maxSelectTextAttributeLength;
            } else {
                return $this->getConfig()->get('maxSelectTextAttributeLengthForList', self::MAX_SELECT_TEXT_ATTRIBUTE_LENGTH);
            }
        }
        return null;
    }

    public function isSkipSelectTextAttributes()
    {
        return $this->skipSelectTextAttributes;
    }

    /**
     * The field that keeps the order of the records linked via $link, null if the relation cannot be sorted.
     */
    public function getRelationSortField(string $link): ?string
    {
        return $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links', $link, 'sortField']);
    }

    public function findLinkedEntities($id, $link, $params)
    {
        $event = $this
            ->dispatchEvent('beforeFindLinkedEntities', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'params' => $params, 'result' => null]));

        $result = $event->getArgument('result');
        if (is_array($result) && array_key_exists('total', $result) && array_key_exists('collection', $result)) {
            return $result;
        }

        $id     = $event->getArgument('id');
        $link   = $event->getArgument('link');
        $params = $event->getArgument('params');

        $this->getPseudoTransactionManager()->runForEntity($this->getEntityType(), $id);

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            return [
                'collection' => new EntityCollection([], $this->entityType),
                'total'      => 0
            ];
        }
        if (!$this->getAcl()->check($entity, 'read')) {
            throw new Forbidden();
        }
        if (empty($link)) {
            throw new Error();
        }

        $methodName = 'findLinkedEntities' . ucfirst($link);
        if (method_exists($this, $methodName)) {
            return $this->$methodName($id, $params);
        }

        $foreignEntityName = $entity->relations[$link]['entity'] ?? null;

        if (empty($foreignEntityName)) {
            return [
                'collection' => new EntityCollection([], $this->entityType),
                'total'      => 0
            ];
        }

        if (!$this->getAcl()->check($foreignEntityName, 'read')) {
            throw new Forbidden();
        }

        $recordService = $this->getRecordService($foreignEntityName);

        $disableCount = $params['disableCount'] ?? false;
        if (
            in_array($this->entityType, $this->getConfig()->get('disabledCountQueryEntityList', []))
        ) {
            $disableCount = true;
        }

        $maxSize = 0;
        if ($disableCount) {
            if (!empty($params['maxSize'])) {
                $maxSize           = $params['maxSize'];
                $params['maxSize'] = $params['maxSize'] + 1;
            }
        }

        $selectParams                   = $this->getSelectManager($foreignEntityName)->getSelectParams($params, true);
        $selectParams['collectionOnly'] = true;

        // the records of a sortable relation are shown in their order unless another order is requested
        if (empty($params['sortBy']) && !empty($orderBy = $this->getRepository()->getRelationSortOrderBy($entity, $link))) {
            $selectParams['orderBy'] = $orderBy;
            $selectParams['order']   = 'ASC';
        }
        if (!empty($params['allAttributes'])) {
            $selectParams['allAttributes'] = true;
        } elseif (!empty($params['attributesIds'])) {
            $selectParams['attributesIds'] = $params['attributesIds'];
        }

        if (array_key_exists($link, $this->linkSelectParams)) {
            $selectParams = array_merge($selectParams, $this->linkSelectParams[$link]);
        }

        $selectParams['maxTextColumnsLength'] = $recordService->getMaxSelectTextAttributeLength();

        $selectAttributeList = $recordService->getSelectAttributeList($params);
        if ($selectAttributeList) {
            $selectParams['select'] = $selectAttributeList;
        } else {
            $selectParams['skipTextColumns'] = $recordService->isSkipSelectTextAttributes();
        }

        if (!empty($params['totalOnly'])) {
            return ['total' => $this->getRepository()->countRelated($entity, $link, $selectParams)];
        }

        // handle relation filter
        if (!empty($selectParams['select'])) {
            $relationName = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'relationName']);
            if (!empty($relationName)) {
                $relationName = ucfirst($relationName);
                if (!empty($params['whereRelation'])) {
                    $relationSelectParams          = $this->getSelectManager($relationName)->getSelectParams(['where' => $params['whereRelation']], true);
                    $selectParams['whereRelation'] = $relationSelectParams['whereClause'];;
                }
            }
        }

        $total      = 0;
        $collection = $this->getRepository()->findRelated($entity, $link, $selectParams);

        if (!empty($collection) && count($collection) > 0) {
            $recordService->prepareCollectionForOutput($collection, $selectParams);

            $relationIds = [];
            foreach ($collection as $e) {
                if (!empty($e->rowData['relation__id'])) {
                    $relationIds[] = $e->rowData['relation__id'];
                }
            }

            // load relation entity if needed
            if (!empty($relationIds) && !empty($relationName)) {
                $relationService    = $this->getServiceFactory()->create($relationName);
                $params             = [
                    'collectionOnly' => true,
                    'skipMeta'       => true,
                    'where'          => [
                        [
                            'attribute' => 'id',
                            'type'      => 'in',
                            'value'     => array_values($relationIds)
                        ]
                    ]
                ];
                $relationCollection = $relationService->findEntities($params)['collection'];
                foreach ($relationIds as $index => $relationId) {
                    $e = $collection[$index];
                    foreach ($relationCollection as $relationEntity) {
                        if ($relationEntity->get('id') === $relationId) {
                            $e->relationEntity = $relationEntity;
                        }
                    }
                }
            }

            foreach ($collection as $e) {
                $recordService->loadAdditionalFieldsForList($e);
                if (!empty($params['loadAdditionalFields'])) {
                    $recordService->loadAdditionalFields($e);
                }
                if (!empty($selectAttributeList)) {
                    $this->loadLinkMultipleFieldsForList($e, $selectAttributeList);

                    if (!empty(array_intersect($selectAttributeList, $this->getScriptFieldsList($e)))) {
                        $this->calculateScriptFields($e);
                    }
                } elseif (!empty($params['calculateScriptFields'])) {
                    $this->calculateScriptFields($e);
                }
                if (RequestHeaders::isMetaRequested()) {
                    $recordService->putMetaForLink($entity, $link, $e);
                    if (RequestHeaders::isMetaGroupRequested('availableActions')) {
                        $recordService->putAclMetaForLink($entity, $link, $e);
                    }
                }
                $recordService->prepareEntityForOutput($e);
            }

            if (empty($params['collectionOnly'])) {
                if (!$disableCount) {
                    $total = $this->getRepository()->countRelated($entity, $link, $selectParams);
                } else {
                    if ($maxSize && count($collection) > $maxSize) {
                        $total = -1;
                        unset($collection[count($collection) - 1]);
                    } else {
                        $total = -2;
                    }
                }
            }
        }

        return $this
            ->dispatchEvent('afterFindLinkedEntities', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'params' => $params, 'result' => ['total' => $total, 'collection' => $collection]]))
            ->getArgument('result');
    }

    protected function prepareUnitFieldValue(Entity $entity, string $fieldName, array $fieldDefs): void
    {
        $mainField = $fieldDefs['mainField'] ?? $fieldName;

        $unitId = $entity->get($mainField . 'UnitId');

        /** @var \Atro\Repositories\Measure $measureRepository */
        $measureRepository = $this->getEntityManager()->getRepository('Measure');

        $entity->set($fieldName . 'UnitData', empty($unitId) ? [] : $measureRepository->getPreparedUnit($fieldDefs['measureId'], $unitId));

        if ($unitId === null) {
            return;
        }

        $value = $entity->get($fieldName);
        if ($value === null) {
            return;
        }
        $units = $this->getMeasureUnits($fieldDefs['measureId']);
        if (empty($units)) {
            return;
        }

        $entity->set($fieldName . 'AllUnits', $fieldDefs['type'] == 'varchar' ? [] : $measureRepository->convertMeasureUnit($value, $fieldDefs['measureId'], $unitId));

        /**
         * Set unit name to virtual field for backward compatibility
         */
        foreach ($measureRepository->getMeasureUnits($fieldDefs['measureId']) as $unit) {
            if ($unit->get('id') === $unitId) {
                $entity->set($fieldName . 'Unit', $unit->get('name'));
                $entity->set($fieldName . 'UnitName', $unit->get('name'));
            }
        }
    }

    public function putMeta(Entity $entity): void
    {
        $this->dispatchEvent('beforePutMeta', new Event(['entity' => $entity, 'service' => $this]));

        if (RequestHeaders::isMetaGroupRequested('audit')) {
            $this->putMetaActorAndDelegator($entity);
        }

        if (RequestHeaders::isMetaGroupRequested('linkOptions')) {
            $this->putMetaColorOptionData($entity);
        }

        if ($this instanceof EditableInterface
            && RequestHeaders::isMetaGroupRequested('readOnlyFields')
            && !empty($fields = $this->getReadOnlyFields($entity))
        ) {
            $meta = $entity->get('_meta') ?? [];
            $meta['readOnlyFields'] = array_values($fields);
            $entity->set('_meta', $meta);
        }
    }

    protected function putMetaActorAndDelegator(Entity $entity): void
    {
        /** @var \Atro\Repositories\User $userRepository */
        $userRepository = $this->getEntityManager()->getRepository('User');

        foreach (['createdBy', 'modifiedBy'] as $field) {
            if (!empty($entity->get("{$field}Id"))) {
                /** @var \Atro\Entities\User $user */
                $user = $this->collectionUsers[$entity->get("{$field}Id")] ?? $userRepository->get($entity->get("{$field}Id"));
                if (empty($user)) {
                    continue;
                }

                if ($user->isGlobalSystemUser()) {
                    $entity->set("{$field}Name", $this->getUserDisplayName($user));
                }

                if ($user->get('actorId') === $user->id) {
                    continue;
                }

                $actor = $userRepository->get($user->get('actorId'));
                if (!empty($actor)) {
                    $delegator = $userRepository->get($user->get('delegatorId'));

                    $entity->set("{$field}Id", $actor->id);
                    $entity->set("{$field}Name", $this->getUserDisplayName($actor));

                    $entity->setMeta('audit', $field, [
                        'actor'     => [
                            'id'       => $entity->get("{$field}Id"),
                            'name'     => $entity->get("{$field}Name"),
                            'isSystem' => $actor->isSystemUser()
                        ],
                        'delegator' => [
                            'id'       => $user->get('delegatorId'),
                            'name'     => !empty($delegator) ? $delegator->get('name') : null,
                            'isSystem' => !empty($delegator) ? $delegator->isSystemUser() : true
                        ]
                    ]);
                }
            }
        }
    }

    protected function putMetaColorOptionData(Entity $entity): void
    {
        foreach ($entity->entityDefs['fields'] ?? $this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'fields'], []) as $name => $defs) {
            if (empty($defs['type']) || !in_array($defs['type'], ['link', 'linkMultiple'])) {
                continue;
            }

            $entityName = $defs['entity']
                ?? $entity->entityDefs['links'][$name]['entity']
                ?? $this->getMetadata()->get("entityDefs.{$entity->getEntityName()}.links.$name.entity");

            if (empty($entityName)) {
                continue;
            }

            if (empty($this->getMetadata()->get("entityDefs.{$entityName}.fields.color"))) {
                continue;
            }

            $select = ['id', 'color', 'name'];
            if (!empty($this->getMetadata()->get("entityDefs.{$entityName}.fields.description"))) {
                $select[] = 'description';
            }

            switch ($defs['type']) {
                case 'link':
                    if (!empty($entity->get($name . 'Id'))) {
                        if (!isset($this->cachedOptionData[$entityName][$entity->get($name . 'Id')])) {
                            $option                                                          = $this->getEntityManager()->getRepository($entityName)
                                ->select($select)
                                ->where(['id' => $entity->get($name . 'Id')])
                                ->findOne();
                            $this->cachedOptionData[$entityName][$entity->get($name . 'Id')] = !empty($option) ? $option->toArray() : null;
                        }
                        if (!empty($this->cachedOptionData[$entityName][$entity->get($name . 'Id')])) {
                            $entity->setMeta('linkOptions', $name, $this->cachedOptionData[$entityName][$entity->get($name . 'Id')]);
                        }
                    }
                    break;
                case 'linkMultiple':
                    if (!empty($entity->get($name . 'Ids'))) {
                        foreach ($entity->get($name . 'Ids') as $id) {
                            if (!isset($this->cachedOptionData[$entityName][$id])) {
                                $options = $this->getEntityManager()->getRepository($entityName)
                                    ->select($select)
                                    ->where(['id' => $entity->get($name . 'Ids')])
                                    ->find();
                                foreach ($options as $option) {
                                    $this->cachedOptionData[$entityName][$option->id] = $option->toArray();
                                }
                                break;
                            }
                        }

                        $res = [];
                        foreach ($entity->get($name . 'Ids') as $id) {
                            if (!empty($this->cachedOptionData[$entityName][$id])) {
                                $res[] = $this->cachedOptionData[$entityName][$id];
                            }
                        }
                        $entity->setMeta('linkOptions', $name, $res);
                    }
                    break;
            }
        }
    }

    public function putMetaForLink(Entity $entityFrom, string $link, Entity $entity): void
    {
        $this->dispatchEvent('beforePutMetaForLink', new Event(['entityFrom' => $entityFrom, 'link' => $link, 'entity' => $entity, 'service' => $this]));

        if (!empty($entity->relationEntity) && RequestHeaders::isMetaGroupRequested('relationRecord')) {
            $this->putMetaRelationRecord($entity);
        }
    }

    /**
     * Puts the record of the relation joining the linked record with the parent one, without its own meta.
     */
    protected function putMetaRelationRecord(Entity $entity): void
    {
        foreach ($entity->relationEntity->toArray() as $field => $value) {
            if ($field !== '_meta') {
                $entity->setMeta('relationRecord', $field, $value);
            }
        }
    }

    public function putAclMeta(Entity $entity): void
    {
        $entity->setAvailableAction('edit', $this instanceof EditableInterface && ($this->getUser()->isAdmin() || $this->getAcl()->check($entity, 'edit')));
        $entity->setAvailableAction('delete', $this instanceof DeletableInterface && ($this->getUser()->isAdmin() || $this->getAcl()->check($entity, 'delete')));
        $entity->setAvailableAction('stream', false);
    }

    public function putAclMetaForLink(Entity $entityFrom, string $link, Entity $entity): void
    {
        $this->putAclMeta($entity);

        $this->dispatchEvent('beforePutAclMetaForLink', new Event(['entityFrom' => $entityFrom, 'link' => $link, 'entity' => $entity, 'service' => $this]));

        if (in_array($link, ['associatedItems', 'associatingItems'])) {
            $entity->setAvailableAction('delete', false);
        }

        $entity->setAvailableAction('unlink', false);

        if ($this->getUser()->isAdmin()) {
            $entity->setAvailableAction('unlink', true);
        } else {
            $foreign = $this->getMetadata()->get("entityDefs.{$entityFrom->getEntityName()}.links.$link.foreign");
            if (!empty($foreign)) {
                switch ($this->getMetadata()->get("entityDefs.{$entity->getEntityName()}.links.$foreign.type")) {
                    case 'belongsTo':
                        $entity->setAvailableAction('unlink', $this->getAcl()->check($entity, 'edit'));
                        break;
                    case 'hasMany':
                        if (!empty($entity->relationEntity)) {
                            $entity->setAvailableAction('unlink', $this->getAcl()->check($entity->relationEntity, 'delete'));
                        }
                        break;
                }
            }
        }

        $this->dispatchEvent('afterPutAclMetaForLink', new Event(['entityFrom' => $entityFrom, 'link' => $link, 'entity' => $entity, 'service' => $this]));
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        $this->prepareOutputValues($entity);

        // prepare dates according to provided timezone
        if (!empty($_GET['timezone'])) {
            $this->modifyEntityFieldsByTimezone($entity, $_GET['timezone']);
        }

        foreach ($entity->entityDefs['fields'] as $field => $fieldDefs) {
            if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'script' && !empty($fieldDefs['script'])
                && $entity->has($field)
                && $entity->get($field) === null
            ) {
                $entity->_realtimeDisabled = true;
                $this->getRepository()->calculateScriptFields($entity);
                break;
            }
        }
    }

    /**
     * The values of the record in the language of the request and the listeners of the output.
     */
    protected function prepareOutputValues(Entity $entity): void
    {
        if (!empty($this->getMemoryStorage()->get('importJobId')) || $this->isPseudoTransaction()) {
            return;
        }

        foreach ($this->internalAttributeList as $field) {
            $entity->clear($field);
        }

        foreach ($this->getAcl()->getScopeForbiddenAttributeList($entity->getEntityType(), 'read') as $attribute) {
            if ($attribute === 'ownerUserId' || $attribute === 'assignedUserId') {
                continue;
            }
            $entity->clear($attribute);
        }

        foreach ($entity->entityDefs['fields'] ?? $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []) as $name => $defs) {
            if (empty($defs['type'])) {
                continue 1;
            }

            switch ($defs['type']) {
                case 'varchar':
                case 'int':
                case 'float':
                    if (!empty($defs['measureId'])) {
                        $this->prepareUnitFieldValue($entity, $name, $defs);
                    }
                    break;
                case 'measure':
                    $unitEntity = null;
                    foreach ($this->getMeasureUnits($defs['measureId']) as $unit) {
                        if ($unit->get('id') === $entity->get($name)) {
                            $unitEntity = $unit;
                            break;
                        }
                    }
                    if (!empty($unitEntity)) {
                        $localizedName = Language::getLocalizedFieldName($this->getEntityManager()->getContainer(), 'Unit', 'name');
                        $entity->set($name . 'Name', $unitEntity->get($localizedName) ?? $unitEntity->get('name'));
                    }
                    break;
            }
        }

        // modify entity if the header language exists
        if (!empty($language = $this->getHeaderLanguage())) {
            foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], []) as $fieldName => $fieldData) {
                if (!empty($fieldData['isMultilang']) && $language !== 'main') {
                    $langField = $fieldName . ucfirst(Util::toCamelCase(strtolower($language)));
                    $entity->set($fieldName, $entity->get($langField));
                }
            }

            foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], []) as $fieldName => $fieldData) {
                if (!empty($fieldData['multilangLocale'])) {
                    $entity->clear($fieldName);
                }
            }

            if (!empty($attributesDefs = $entity->get('attributesDefs'))) {
                foreach ($attributesDefs as $attributeName => $attributeData) {
                    if (!empty($attributeData['multilangLocale']) && $language !== 'main') {
                        if ($attributeData['multilangLocale'] === $language) {
                            $entity->set($attributeData['multilangField'], $entity->get($attributeName));
                        }
                        $entity->clear($attributeName);
                    }
                }
            }
        }

        $this->dispatchEvent('prepareEntityForOutput', new Event(['entity' => $entity, 'service' => $this]));
    }

    public function getSelectAttributeList($params)
    {
        if ($this->forceSelectAllAttributes) {
            return null;
        }

        if ($this->selectAttributeList) {
            return $this->selectAttributeList;
        }

        $seed = $this->getEntityManager()->getEntityFactory()->create($this->getEntityType());

        if (array_key_exists('select', $params)) {
            $passedAttributeList = array_map('trim', $params['select']);
        } else {
            $passedAttributeList = null;
        }

        if ($passedAttributeList) {
            $attributeList = [];
            if (!in_array('id', $passedAttributeList)) {
                $attributeList[] = 'id';
            }
            $aclAttributeList = ['assignedUserId', 'createdById'];

            foreach (['read', 'edit', 'delete'] as $action) {
                if ($this->getAcl()->getLevel($this->getEntityType(), $action) === 'team') {
                    $aclAttributeList[] = 'teamsIds';
                    break;
                }
            }

            foreach ($aclAttributeList as $attribute) {
                if (!in_array($attribute, $passedAttributeList) && $seed->hasAttribute($attribute)) {
                    $attributeList[] = $attribute;
                }
            }

            foreach ($passedAttributeList as $attribute) {
                if (!in_array($attribute, $attributeList) && $seed->hasAttribute($attribute)) {
                    $fieldDefs = $this->getMetadata()->get(['entityDefs', $seed->getEntityType(), 'fields', $attribute]);

                    $attributeList[] = $attribute;
                    if (
                        empty($fieldDefs['notStorable'])
                        && empty($fieldDefs['relationVirtualField'])
                        && !empty($fieldDefs['multilangField'])
                        && !in_array($fieldDefs['multilangField'], $attributeList)
                    ) {
                        $attributeList[] = $fieldDefs['multilangField'];
                    }

                    if (!empty($fieldDefs['measureId']) && !empty($fieldDefs['type']) && in_array($fieldDefs['type'], ['int', 'float', 'varchar'])) {
                        $attributeName   = $fieldDefs['mainField'] ?? $attribute;
                        $attributeList[] = $attributeName;
                        $attributeList[] = $attributeName . 'UnitId';
                    }
                }
            }

            if (!empty($params['sortBy'])) {
                $sortByField     = $params['sortBy'];
                $sortByFieldType = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $sortByField, 'type']);

                $sortByAttributeList = $this->getFieldManagerUtil()->getAttributeList($this->getEntityType(), $sortByField);
                foreach ($sortByAttributeList as $attribute) {
                    if (!in_array($attribute, $attributeList) && $seed->hasAttribute($attribute)) {
                        $attributeList[] = $attribute;
                    }
                }
            }

            foreach ($this->mandatorySelectAttributeList as $attribute) {
                if (!in_array($attribute, $attributeList) && $seed->hasAttribute($attribute)) {
                    $attributeList[] = $attribute;
                }
            }

            foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], []) as $fieldName => $fieldDefs) {
                if (!empty($fieldDefs['mandatoryToSelect'])) {
                    if (empty($fieldDefs['type'])) {
                        continue;
                    }
                    if ($fieldDefs['type'] === 'link') {
                        if (!in_array("{$fieldName}Id", $attributeList)) {
                            $attributeList[] = "{$fieldName}Id";
                        }
                        if (!in_array("{$fieldName}Name", $attributeList)) {
                            $attributeList[] = "{$fieldName}Name";
                        }
                    } elseif ($fieldDefs['type'] === 'linkMultiple') {
                        if (!in_array("{$fieldName}Ids", $attributeList)) {
                            $attributeList[] = "{$fieldName}Ids";
                        }
                        if (!in_array("{$fieldName}Names", $attributeList)) {
                            $attributeList[] = "{$fieldName}Names";
                        }
                    } else {
                        if (!in_array($fieldName, $attributeList)) {
                            $attributeList[] = $fieldName;
                        }
                    }
                }
            }

            if (!empty($language = $this->getHeaderLanguage()) && $language !== 'main') {
                $newAttributeList = [];
                foreach ($attributeList as $field) {
                    $newAttributeList[] = $field;
                    if ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $field, 'isMultilang'])) {
                        $languageField = Util::toCamelCase($field . '_' . strtolower($language));
                        if ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $languageField])) {
                            $newAttributeList[] = $languageField;
                        }
                    }
                }
                $attributeList = $newAttributeList;
            }

            return $attributeList;
        }

        return null;
    }

    /**
     * @param string $action
     * @param Event  $event
     *
     * @return Event
     */
    protected function dispatchEvent(string $action, Event $event): Event
    {
        // set target
        $event->setArgument('target', $this->entityType . 'Service');

        // dispatch common listener
        $this->getInjection('eventManager')->dispatch('Service', $action, $event);

        // dispatch target listener
        $event = $this->getInjection('eventManager')->dispatch($event->getArgument('target'), $action, $event);

        // a subtype record is the supertype record, the listeners of the supertype service apply to it as well
        $supertype = $this->getMetadata()->get(['scopes', $this->entityType, 'supertypeId']);
        if (empty($supertype)) {
            return $event;
        }

        return $this->getInjection('eventManager')->dispatch($supertype . 'Service', $action, $event);
    }

    protected function getPseudoTransactionManager(): PseudoTransactionManager
    {
        return $this->getInjection('pseudoTransactionManager');
    }

    protected function getMeasureUnits(string $measureId): array
    {
        if (!isset($this->measureUnits[$measureId])) {
            $this->measureUnits[$measureId] = $this->getEntityManager()->getRepository('Measure')
                ->getMeasureUnits($measureId);
        }

        return $this->measureUnits[$measureId];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('twig');
    }

    protected function getNameFieldUtil(): NameField
    {
        return $this->getEntityManager()->getContainer()->get(NameField::class);
    }

    protected function getConditionChecker(): ConditionChecker
    {
        return $this->getInjection('condition');
    }

    /**
     * Resolves $id to the entity's real id in a single query, allowing it to also be looked up by its `isSlug`
     * field (when the entity type has one). Returns null (leaving $id untouched) when there's no slug field to
     * fall back on, so the caller's normal id-only lookup path is used as-is.
     */
    public function resolveIdBySlug(string $id): ?string
    {
        $slugField = $this->getSlugField();
        if ($slugField === null) {
            return null;
        }

        if (in_array($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $slugField, 'type']), ['int', 'autoincrement'])) {
            if (is_numeric($id)) {
                $id = (int)$id;
            } else {
                return null;
            }
        }

        $record = $this->getRepository()
            ->where(['OR' => ['id' => $id, $slugField => $id]])
            ->findOne();

        if (!empty($record)) {
            $this->getRepository()->putToCache($record->get('id'), $record);
        }

        return !empty($record) ? $record->get('id') : null;
    }

    protected function getSlugField(): ?string
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], []) as $field => $defs) {
            if (!empty($defs['isSlug'])) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Renders the script on the first record of the entity, e.g. to show the preview of a script field.
     */
    public function previewScript(string $script, string $outputType = 'text'): array
    {
        $entity = $this->getRepository()->order('id', 'ASC')->findOne();
        if (!empty($entity) && !empty($this->getMetadata()->get(['scopes', $this->entityType, 'hasAttribute']))) {
            $this->getInjection(\Atro\Core\AttributeFieldConverter::class)->putAttributesToEntity($entity);
        }

        $preview = $this->getInjection('twig')->renderTemplate($script, ['entity' => $entity], $outputType);
        if (is_string($preview) && $outputType !== 'wysiwyg') {
            $outputType = 'text';
        }

        return [
            'preview'    => $preview,
            'entityName' => $this->entityType,
            'entity'     => empty($entity) ? null : $entity->toArray(),
            'outputType' => $outputType,
        ];
    }

    public function modifyEntityFieldsByTimezone(Entity $entity, string $timezone): void
    {
        $fields = $this->getMetadata()->get("entityDefs.{$entity->getEntityName()}.fields", []);
        foreach ($fields as $field => $fieldDefs) {
            if (empty($fieldDefs['type']) || !in_array($fieldDefs['type'], ['date', 'datetime'])) {
                continue;
            }
            if (empty($entity->get($field))) {
                continue;
            }

            try {
                $date = new \DateTime($entity->get($field));
                $date->setTimezone(new \DateTimeZone($timezone));
                $entity->set($field, $date->format('Y-m-d H:i:s'));
            } catch (\Throwable $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    protected function getAllowedMassActions(): array
    {
        $actions = ['action', 'download'];
        if ($this instanceof MassActionsInterface) {
            $actions = array_merge($actions, ['update', 'delete', 'restore']);
        }

        return $actions;
    }

    public function executeMassAction(array $params, ?\Closure $actionOperation = null): array
    {
        if (empty($params['action']) || !is_int($params['maxCountWithoutJob']) || empty($params['maxChunkSize']) || empty($params['minChunkSize'])) {
            return [];
        }

        $action             = $params['action'];
        $maxCountWithoutJob = $params['maxCountWithoutJob'];
        $maxChunkSize       = $params['maxChunkSize'];
        $minChunkSize       = $params['minChunkSize'];
        $maxConcurrentJobs  = $this->getConfig()->get('maxConcurrentJobs', 6);

        $allowMassActions = array_merge(
            $this->getAllowedMassActions(),
            $this->getMetadata()->get(['clientDefs', $this->getEntityType(), 'massActionList'], [])
        );

        if (
            !in_array($action, $allowMassActions)
            && empty($this->getMetadata()->get(['clientDefs', $this->getEntityType(), 'massActions', $action]))
        ) {
            return [];
        }

        $actionEntity = null;
        if ($action === 'action' && !empty($params['additionalJobData']['actionId'])) {
            $actionEntity = $this->getEntityManager()->getEntity('Action', $params['additionalJobData']['actionId']);
            if (empty($actionEntity)) {
                return [];
            }
        }

        $repository = $this->getEntityManager()->getRepository($this->entityType);
        $byWhere    = array_key_exists('where', $params);
        $errors     = [];

        if (array_key_exists('ids', $params) && !empty($params['ids']) && is_array($params['ids'])) {
            $ids   = $params['ids'];
            $total = count($ids);
        } elseif ($byWhere) {
            $selectParams = $this->getSelectParams(['where' => $params['where']], true, true);
            if ($action === 'delete' && !empty($params['permanently'])) {
                $selectParams['withDeleted'] = true;
            }

            $repository->handleSelectParams($selectParams);
            $total = $repository->count(array_merge($selectParams, ['select' => ['id']]));
        } else {
            $ids   = [];
            $total = 0;
        }

        if ($total <= $maxCountWithoutJob && !empty($actionOperation)) {
            if ($byWhere) {
                $collection = $repository->find(array_merge($selectParams, ['select' => ['id']]));
                $ids        = array_column($collection->toArray(), 'id');
            }
            foreach ($ids as $id) {
                try {
                    $actionOperation($id);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error("{$action} {$this->getEntityType()} '$id' failed: {$e->getMessage()}");
                    $entity   = $this->getRepository()->where(['id' => $id])->findOne(['withDeleted' => true]);
                    $name     = !empty($entity) ? $entity->get('name') : $id;
                    $errors[] = "Error for '$name': {$e->getMessage()}";
                }
            }

            if (!empty($errors)) {
                $label = "mass" . ucfirst($action);
                $label .= count($errors) === count($ids) ? "NoRecordProceed" : "SomeRecordNotProceed";
                array_unshift($errors, $this->getInjection('language')->translate($label, 'exceptions'));
            }

            return [$total, $errors, true];
        } else {
            $chunkSize = Util::getChunkSize($total, $maxChunkSize, $minChunkSize, $maxConcurrentJobs);

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'     => sprintf(
                    $this->getInjection('language')->translate('massActionCreatorJobName'),
                    empty($actionEntity) ? $this->getInjection('language')->translate($action, 'massActions', $this->entityType) : $actionEntity->get('name'),
                    $this->entityType
                ),
                'type'     => 'MassActionCreator',
                'priority' => $this->entityType === 'Job' ? 300 : 100,
                'payload'  => [
                    'ids'        => $ids ?? [],
                    'action'     => $action,
                    'entityName' => $this->entityType,
                    'chunkSize'  => (int)$chunkSize,
                    'total'      => $total,
                    'params'     => $params,
                ]
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);

            return [$total, $errors, false, $jobEntity];
        }
    }

    public function getParamsForTree(string $link, string $scope, array $params): array
    {
        if (!empty($link) && $link !== '_self') {
            $foreignLink = '';
            foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links']) ?? [] as $linkName => $linkData) {
                if (!empty($linkData['foreign']) && $linkData['foreign'] === $link && $linkData['entity'] === $scope) {
                    $foreignLink = $linkName;
                    break;
                }
            }

            if (empty($foreignLink)) {
                foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'fields']) ?? [] as $fieldName => $fieldData) {
                    if ($fieldData['type'] === 'link' && !empty($fieldData['entity']) && $fieldData['entity'] === $scope) {
                        $foreignLink = $fieldName;
                        break;
                    }
                }
            }

            if ($this->getMetadata()->get(['scopes', $this->entityName, 'type']) === 'ReferenceData') {
                $field             = $link . 'Id';
                $foreignRepository = $this->getEntityManager()->getRepository($scope);

                $params['foreignWhere'][] = [
                    'type'      => 'isNotNull',
                    'attribute' => $field,
                ];

                $sp           = $this->getSelectManager($scope)->getSelectParams(['where' => $params['foreignWhere']], true, true);
                $sp['select'] = [$field];
                $qb1          = $foreignRepository->getMapper()->createSelectQueryBuilder($foreignRepository->get(), $sp);

                $ids = $qb1->distinct()->fetchFirstColumn();

                $params['where'][] = [
                    'type'      => 'in',
                    'attribute' => 'id',
                    'value'     => $ids,
                ];
            } else if (!empty($foreignLink)) {
                $where = [
                    'type'      => 'isLinked',
                    'attribute' => $foreignLink,
                ];
                if (!empty($params['foreignWhere'])) {
                    $where['type']     = 'linkedWith';
                    $where['subQuery'] = $params['foreignWhere'];
                }
                $params['where'][] = $where;
            } else {
                $field = $link . 'Id';
                if ($link === 'teams') {
                    // TODO:  apply main filter
                    $params['queryCallbacks'] = [
                        function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($scope) {
                            $ta = $mapper->getQueryConverter()->getMainTableAlias();

                            $qb->innerJoin($ta, 'entity_team', 'et', "$ta.id = et.team_id")
                                ->innerJoin('et', $mapper->toDb($scope), 'ts', "et.entity_id = ts.id")
                                ->andWhere("et.entity_id is not null")
                                ->andWhere("et.deleted = :false")
                                ->andWhere("ts.deleted = :false")
                                ->setParameter('false', false, ParameterType::BOOLEAN);
                        }
                    ];
                    $params['distinct']       = true;
                } else if (!empty($this->getEntityManager()->getOrmMetadata()->get($scope, 'fields')[$field])) {
                    if (!empty($params['foreignWhere'])) {
                        $params['where'][] = [
                            'type'          => 'in',
                            'attribute'     => 'id',
                            'subQuery'      => $params['foreignWhere'],
                            'foreignEntity' => $scope,
                            'foreignField'  => $field,
                        ];
                    } else {
                        $params['queryCallbacks'] = [
                            function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($field, $scope) {
                                $ta     = $mapper->getQueryConverter()->getMainTableAlias();
                                $column = $mapper->toDb($field);

                                $qb->leftJoin($ta, $mapper->toDb($scope), 'et', "$ta.id = et.$column")
                                    ->andWhere("et.$column is not null")
                                    ->andWhere("et.deleted = :false")
                                    ->setParameter('false', false, ParameterType::BOOLEAN);
                            }
                        ];
                        $params['distinct']       = true;
                    }
                } else {
                    throw new BadRequest("Field $field not found on $scope and Foreign link not found for ($scope: $link) on " . $this->entityName);
                }
            }
        }
        unset($params['foreignWhere']);

        return $params;
    }

    public function getTreeItems(string $link, string $scope, array $params): array
    {
        if (!$this->getAcl()->check($this->entityName, 'read')) {
            throw new Forbidden();
        }

        $params = $this->getParamsForTree($link, $scope, $params);

        $repository = $this->getRepository();

        $selectParams = $this->getSelectManager($this->entityType)->getSelectParams($params, true, true);
        if (!empty($params['distinct'])) {
            $selectParams['distinct'] = true;
        }

        if (!empty($params['selectedId']) && method_exists($repository, 'getRecordPosition')) {
            $position = $repository->getRecordPosition((string)$params['selectedId'], $selectParams);
            if ($position !== null) {
                $limit  = $params['maxSize'];
                $index  = $position - 1;
                $offset = $index - $limit < 0 ? 0 : $index - $limit;

                $params['offset']       = $offset;
                $selectParams['offset'] = $offset;
                $selectParams['limit']  = $index - $offset + $limit;
            }
        }

        $fields             = ['id', $this->getNameFieldUtil()->getField($this->entityName)];
        $localizedNameField = $this->getNameFieldUtil()->getLocalizedField($this->entityName);
        if (!empty($localizedNameField)) {
            $fields[] = $localizedNameField;
        }

        if (!empty($selectParams['orderBy']) && !in_array($selectParams['orderBy'], $fields)) {
            $fields[] = $selectParams['orderBy'];
        }
        $selectParams['select'] = $fields;
        $collection             = $repository->find($selectParams);
        $total                  = $repository->count($selectParams);
        $offset                 = $params['offset'];
        $result                 = [];

        foreach ($collection as $key => $item) {
            $value    = $this->getNameFieldUtil()->getLocalizedValue($item, $this->entityName);
            $result[] = [
                'id'             => $item->get('id'),
                'name'           => !empty($value) ? $value : $item->get('id'),
                'offset'         => $offset + $key,
                'total'          => $total,
                'disabled'       => false,
                'load_on_demand' => false,
                'scope'          => $this->entityName,
            ];
        }

        return [
            'list'  => $result,
            'total' => $total
        ];
    }
}
