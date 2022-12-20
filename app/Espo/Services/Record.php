<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

namespace Espo\Services;

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\PseudoTransactionManager;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;
use Treo\Core\Exceptions\NotModified;
use Treo\Core\Utils\Condition\Condition;

class Record extends \Espo\Core\Services\Base
{
    protected $dependencies = array(
        'entityManager',
        'user',
        'preferences',
        'metadata',
        'acl',
        'aclManager',
        'config',
        'serviceFactory',
        'fileManager',
        'selectManagerFactory',
        'fileStorageManager',
        'injectableFactory',
        'fieldManagerUtil',
        'eventManager',
        'language',
        'pseudoTransactionManager'
    );

    protected $getEntityBeforeUpdate = false;

    protected $entityName;

    protected $entityType;

    private $streamService;

    protected $notFilteringAttributeList =[]; // TODO maybe remove it

    protected $internalAttributeList = [];

    protected $readOnlyAttributeList = [];

    protected $readOnlyLinkList = [];

    protected $linkSelectParams = [];

    protected $noEditAccessRequiredLinkList = [];

    protected $checkForDuplicatesInUpdate = false;

    protected $actionHistoryDisabled = false;

    protected $duplicatingLinkList = [];

    protected $listCountQueryDisabled = false;

    protected $maxSelectTextAttributeLength = null;

    protected $maxSelectTextAttributeLengthDisabled = false;

    protected $skipSelectTextAttributes = false;

    protected $selectAttributeList = null;

    protected $mandatorySelectAttributeList = [];

    protected $forceSelectAllAttributes = false;

    protected string $pseudoTransactionId = '';

    protected int $maxMassUpdateCount = 20;
    protected int $maxMassLinkCount = 20;
    protected int $maxMassUnlinkCount = 20;

    /**
     * @var bool|array
     */
    private $relationFields = false;

    const MAX_SELECT_TEXT_ATTRIBUTE_LENGTH = 5000;

    const FOLLOWERS_LIMIT = 4;

    public function __construct()
    {
        parent::__construct();
        if (empty($this->entityType)) {
            $name = get_class($this);
            if (preg_match('@\\\\([\w]+)$@', $name, $matches)) {
                $name = $matches[1];
            }
            if ($name != 'Record') {
                $this->entityType = Util::normilizeScopeName($name);
            }
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
        if ($this->getServiceFactory()->checkExists($name)) {
            $service = $this->getServiceFactory()->create($name);
        } else {
            $service = $this->getServiceFactory()->create('Record');
            $service->setEntityType($name);
        }

        return $service;
    }

    protected function processActionHistoryRecord($action, Entity $entity)
    {
        if ($this->actionHistoryDisabled) return;
        if ($this->getConfig()->get('actionHistoryDisabled')) return;

        $historyRecord = $this->getEntityManager()->getEntity('ActionHistoryRecord');

        $historyRecord->set('action', $action);
        $historyRecord->set('userId', $this->getUser()->id);
        $historyRecord->set('authTokenId', $this->getUser()->get('authTokenId'));
        $historyRecord->set('ipAddress', $this->getUser()->get('ipAddress'));
        $historyRecord->set('authLogRecordId', $this->getUser()->get('authLogRecordId'));

        if ($entity) {
            $historyRecord->set(array(
                'targetType' => $entity->getEntityType(),
                'targetId' => $entity->id
            ));
        }

        $this->getEntityManager()->saveEntity($historyRecord);
    }

    public function readEntity($id)
    {
        $id = $this
            ->dispatchEvent('beforeReadEntity', new Event(['id' => $id]))
            ->getArgument('id');

        if (empty($id)) {
            throw new Error();
        }
        $entity = $this->getEntity($id);

        if ($entity) {
            $this->processActionHistoryRecord('read', $entity);
        }

        return $this
            ->dispatchEvent('afterReadEntity', new Event(['id' => $id, 'entity' => $entity]))
            ->getArgument('entity');
    }

    public function getEntity($id = null)
    {
        $id = $this
            ->dispatchEvent('beforeGetEntity', new Event(['id' => $id]))
            ->getArgument('id');

        $this->getPseudoTransactionManager()->runForEntity($this->getEntityType(), $id);

        $entity = $this->getRepository()->get($id);
        if (!empty($entity) && !empty($id)) {
            $this->loadAdditionalFields($entity);

            if (!$this->getAcl()->check($entity, 'read')) {
                throw new Forbidden();
            }
        }
        if (!empty($entity)) {
            $this->prepareEntityForOutput($entity);
        }

        return $this
            ->dispatchEvent('afterGetEntity', new Event(['id' => $id, 'entity' => $entity]))
            ->getArgument('entity');
    }

    protected function getStreamService()
    {
        if (empty($this->streamService)) {
            $this->streamService = $this->getServiceFactory()->create('Stream');
        }
        return $this->streamService;
    }

    protected function loadIsFollowed(Entity $entity)
    {
        if ($this->getStreamService()->checkIsFollowed($entity)) {
            $entity->set('isFollowed', true);
        } else {
            $entity->set('isFollowed', false);
        }
    }

    protected function loadFollowers(Entity $entity)
    {
        if ($this->getUser()->isPortal()) return;
        if (!$this->getMetadata()->get(['scopes', $entity->getEntityType(), 'stream'])) return;

        $data = $this->getStreamService()->getEntityFollowers($entity, 0, self::FOLLOWERS_LIMIT);
        if ($data) {
            $entity->set('followersIds', $data['idList']);
            $entity->set('followersNames', $data['nameMap']);
        }
    }

    protected function loadLinkMultipleFields(Entity $entity)
    {
        $fieldDefs = $this->getMetadata()->get('entityDefs.' . $entity->getEntityType() . '.fields', array());
        foreach ($fieldDefs as $field => $defs) {
            if (isset($defs['type']) && in_array($defs['type'], ['linkMultiple', 'attachmentMultiple']) && empty($defs['noLoad'])) {
                $columns = null;
                if (!empty($defs['columns'])) {
                    $columns = $defs['columns'];
                }
                $entity->loadLinkMultipleField($field, $columns);
            }
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
        $fieldDefs = $this->getMetadata()->get('entityDefs.' . $entity->getEntityType() . '.fields', array());
        $linkDefs = $this->getMetadata()->get('entityDefs.' . $entity->getEntityType() . '.links', array());
        foreach ($fieldDefs as $field => $defs) {
            if (isset($defs['type']) && $defs['type'] === 'link') {
                if (!empty($defs['noLoad'])) continue;
                if (empty($linkDefs[$field])) continue;
                if (empty($linkDefs[$field]['type'])) continue;
                if ($linkDefs[$field]['type'] !== 'hasOne') continue;

                $entity->loadLinkField($field);
            }
        }
    }

    protected function loadParentNameFields(Entity $entity)
    {
        $fieldDefs = $this->getMetadata()->get('entityDefs.' . $entity->getEntityType() . '.fields', array());
        foreach ($fieldDefs as $field => $defs) {
            if (isset($defs['type']) && $defs['type'] == 'linkParent') {
                $parentId = $entity->get($field . 'Id');
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
                    $idField = $link . 'Id';
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

    public function loadAdditionalFields(Entity $entity)
    {
        $this->loadLinkFields($entity);
        $this->loadLinkMultipleFields($entity);
        $this->loadParentNameFields($entity);
        $this->loadIsFollowed($entity);
        $this->loadFollowers($entity);
        $this->loadNotJoinedLinkFields($entity);
        $this->loadPreview($entity);
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        $this->loadPreviewForCollection($collection);

        $this->dispatchEvent('prepareCollectionForOutput', new Event(['collection' => $collection, 'selectParams' => $selectParams]));
    }

    public function loadPreviewForCollection(EntityCollection $collection): void
    {
        $this->dispatchEvent('loadPreviewForCollection', new Event(['collection' => $collection]));

        $fields = [];
        foreach ($this->getMetadata()->get(['entityDefs', $collection->getEntityName(), 'fields'], []) as $field => $data) {
            if (in_array($data['type'], ['asset', 'image', 'file'])) {
                $fields[] = $field;
            }
        }

        if (empty($fields)) {
            return;
        }

        $ids = [];
        foreach ($fields as $field) {
            foreach ($collection as $entity) {
                $ids[] = $entity->get("{$field}Id");
            }
        }

        $attachmentRepository = $this->getEntityManager()->getRepository('Attachment');
        foreach ($attachmentRepository->where(['id' => $ids])->find() as $attachment) {
            $attachments[$attachment->get('id')] = [
                'name'      => $attachment->get('name'),
                'pathsData' => $attachmentRepository->getAttachmentPathsData($attachment),
            ];
        }

        foreach ($fields as $field) {
            foreach ($collection as $entity) {
                $attachmentId = $entity->get("{$field}Id");
                if (isset($attachments[$attachmentId])) {
                    $entity->set("{$field}Id", $attachmentId);
                    $entity->set("{$field}Name", $attachments[$attachmentId]['name']);
                    $entity->set("{$field}PathsData", $attachments[$attachmentId]['pathsData']);
                } else {
                    $entity->set("{$field}Id", null);
                    $entity->set("{$field}Name", null);
                }
            }
        }
    }

    public function loadPreview(Entity $entity): void
    {
        $fields = [];
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []) as $field => $data) {
            if (in_array($data['type'], ['asset', 'image', 'file']) && !empty($entity->get("{$field}Id"))) {
                $fields[$field] = $entity->get("{$field}Id");
            }
        }

        if (empty($fields)) {
            return;
        }

        /** @var \Espo\Repositories\Attachment $attachmentRepository */
        $attachmentRepository = $this->getEntityManager()->getRepository('Attachment');

        $attachments = $attachmentRepository
            ->where(['id' => array_unique(array_values($fields))])
            ->find();

        foreach (array_keys($fields) as $field) {
            $entity->set("{$field}Id", null);
            $entity->set("{$field}Name", null);
        }

        if (!empty($attachments) && count($attachments) > 0) {
            foreach ($attachments as $attachment) {
                foreach ($fields as $field => $attachmentId) {
                    if ($attachment->id == $attachmentId) {
                        $entity->set("{$field}Id", $attachment->get('id'));
                        $entity->set("{$field}Name", $attachment->get('name'));
                        $entity->set("{$field}PathsData", $attachmentRepository->getAttachmentPathsData($attachment));
                    }
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

    protected function storeEntity(Entity $entity)
    {
        $result = null;

        try {
            $result = $this->getRepository()->save($entity, $this->getDefaultRepositoryOptions());
        } catch (\PDOException $e) {
            if (!empty($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                $message = $e->getMessage();
                $tableName = Util::toUnderScore($entity->getEntityType());

                if (preg_match("/SQLSTATE\[23000\]: Integrity constraint violation: 1062 Duplicate entry '(.*)' for key '(.*)'/", $message, $matches) && !empty($matches[2])) {
                    $keyNameParts = explode('.', $matches[2]);
                    $keyName = array_pop($keyNameParts);
                    $data = $this
                        ->getEntityManager()
                        ->getPDO()
                        ->query("SHOW INDEX FROM $tableName WHERE Key_name = '$keyName' AND Seq_in_index = 1")
                        ->fetch(\PDO::FETCH_ASSOC);

                    if (!empty($data['Column_name'])) {
                        $column = $data['Column_name'];

                        /** @var Language $language */
                        $language = $this->getInjection('language');

                        $column = $language->translate(Util::toCamelCase($column), 'fields', $entity->getEntityType());
                        $errorMessage = sprintf($language->translate('fieldShouldMustBeUnique', 'exceptions'), $column);

                        throw new BadRequest($errorMessage);
                    }
                }
            }

            throw $e;
        }

        return $result;
    }

    protected function checkRequiredFields(Entity $entity, \stdClass $data): bool
    {
        if ($entity->isSkippedValidation('requiredField')) {
            return true;
        }

        if ($this->hasCompleteness($entity)) {
            return true;
        }

        /** @var Language $language */
        $language = $this->getInjection('language');

        foreach ($this->getRequiredFields($entity, $data) as $field) {
            if ($this->isNullField($entity, $field)) {
                $message = sprintf($language->translate('fieldIsRequired', 'exceptions'), htmlentities($language->translate($field, 'fields', $entity->getEntityType())));
                throw (new BadRequest($message))->setDataItem('field', $field);
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     *
     * @throws BadRequest
     * @throws Error
     */
    protected function checkFieldsWithPattern(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields']) as $field => $defs) {
            if (!empty($defs['pattern']) && !empty($entity->get($field))) {
                $this->validateFieldWithPattern($entity, $field, $defs);
            }
        }
    }

    /**
     * @param Entity $entity
     * @param string $field
     * @param array $defs
     *
     * @throws BadRequest
     * @throws Error
     */
    protected function validateFieldWithPattern(Entity $entity, string $field, array $defs): void
    {
        if (empty($defs['pattern'])) {
            return;
        }

        $pattern = $defs['pattern'];
        if (!preg_match($pattern, $entity->get($field))) {
            $message = $this->getInjection('language')->translate('dontMatchToPattern', 'exceptions', $entity->getEntityType());
            $message = str_replace('{field}', $field, $message);
            $message = str_replace('{pattern}', $pattern, $message);

            throw new BadRequest($message);
        }
    }

    /**
     * @param Entity    $entity
     * @param \stdClass $data
     *
     * @return array
     */
    protected function getRequiredFields(Entity $entity, \stdClass $data): array
    {
        $fields = [];

        foreach ($entity->getAttributes() as $field => $fieldData) {
            if (!empty($fieldData['isLinkEntity']) || !empty($fieldData['isLinkEntityName']) || !empty($fieldData['isLinkMultipleCollection'])) {
                continue 1;
            }
            if (!empty($fieldData['required']) || $this->isRequiredField($field, $entity, 'required')) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    protected function hasCompleteness(Entity $entity): bool
    {
        if (!$this->getMetadata()->isModuleInstalled('Completeness')) {
            return false;
        }

        return !empty($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'hasCompleteness']));
    }

    public function checkAssignment(Entity $entity)
    {
        if (!$this->isPermittedAssignedUser($entity)) {
            return false;
        }
        if (!$this->isPermittedOwnerUser($entity)) {
            return false;
        }
        if (!$this->isPermittedTeams($entity)) {
            return false;
        }
        if ($entity->hasLinkMultipleField('assignedUsers')) {
            if (!$this->isPermittedAssignedUsers($entity)) {
                return false;
            }
        }
        return true;
    }

    public function isPermittedAssignedUsers(Entity $entity)
    {
        if (!$entity->hasLinkMultipleField('assignedUsers')) {
            return true;
        }

        if ($this->getUser()->isPortal()) {
            if (count($entity->getLinkMultipleIdList('assignedUsers')) === 0) {
                return true;
            }
        }

        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if ($assignmentPermission === true || $assignmentPermission === 'yes' || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        $toProcess = false;

        if (!$entity->isNew()) {
            $userIdList = $entity->getLinkMultipleIdList('assignedUsers');
            if ($entity->isAttributeChanged('assignedUsersIds')) {
                $toProcess = true;
            }
        } else {
            $toProcess = true;
        }

        $userIdList = $entity->getLinkMultipleIdList('assignedUsers');

        if ($toProcess) {
            if (empty($userIdList)) {
                if ($assignmentPermission == 'no') {
                    return false;
                }
                return true;
            }
            $fetchedAssignedUserIdList = $entity->getFetched('assignedUsersIds');

            if ($assignmentPermission == 'no') {
                foreach ($userIdList as $userId) {
                    if (!$entity->isNew() && in_array($userId, $fetchedAssignedUserIdList)) continue;
                    if ($this->getUser()->id != $userId) {
                        return false;
                    }
                }
            } else if ($assignmentPermission == 'team') {
                $teamIdList = $this->getUser()->getLinkMultipleIdList('teams');
                foreach ($userIdList as $userId) {
                    if (!$entity->isNew() && in_array($userId, $fetchedAssignedUserIdList)) continue;
                    if (!$this->getEntityManager()->getRepository('User')->checkBelongsToAnyOfTeams($userId, $teamIdList)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    public function isPermittedOwnerUser(Entity $entity): bool
    {
        if (!$entity->hasAttribute('ownerUserId')) {
            return true;
        }

        $ownerUserId = $entity->get('ownerUserId');

        if ($this->getUser()->isPortal()) {
            if (!$entity->isAttributeChanged('ownerUserId') && empty($ownerUserId)) {
                return true;
            }
        }

        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if ($assignmentPermission === true || $assignmentPermission === 'yes' || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('ownerUserId')) {
                $toProcess = true;
            }
        } else {
            $toProcess = true;
        }

        if ($toProcess) {
            if (empty($ownerUserId)) {
                if ($assignmentPermission == 'no') {
                    return false;
                }
                return true;
            }
            if ($assignmentPermission == 'no') {
                if ($this->getUser()->id != $ownerUserId) {
                    return false;
                }
            } else if ($assignmentPermission == 'team') {
                $teamIdList = $this->getUser()->get('teamsIds');
                if (!$this->getEntityManager()->getRepository('User')->checkBelongsToAnyOfTeams($ownerUserId, $teamIdList)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function isPermittedAssignedUser(Entity $entity)
    {
        if (!$entity->hasAttribute('assignedUserId')) {
            return true;
        }

        $assignedUserId = $entity->get('assignedUserId');

        if ($this->getUser()->isPortal()) {
            if (!$entity->isAttributeChanged('assignedUserId') && empty($assignedUserId)) {
                return true;
            }
        }

        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if ($assignmentPermission === true || $assignmentPermission === 'yes' || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        $toProcess = false;

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('assignedUserId')) {
                $toProcess = true;
            }
        } else {
            $toProcess = true;
        }

        if ($toProcess) {
            if (empty($assignedUserId)) {
                if ($assignmentPermission == 'no') {
                    return false;
                }
                return true;
            }
            if ($assignmentPermission == 'no') {
                if ($this->getUser()->id != $assignedUserId) {
                    return false;
                }
            } else if ($assignmentPermission == 'team') {
                $teamIdList = $this->getUser()->get('teamsIds');
                if (!$this->getEntityManager()->getRepository('User')->checkBelongsToAnyOfTeams($assignedUserId, $teamIdList)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function isPermittedTeams(Entity $entity)
    {
        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if (empty($assignmentPermission) || $assignmentPermission === true || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        if (!$entity->hasLinkMultipleField('teams')) {
            return true;
        }
        $teamIdList = $entity->getLinkMultipleIdList('teams');
        if (empty($teamIdList)) {
            if ($assignmentPermission === 'team') {
                if ($entity->hasLinkMultipleField('assignedUsers')) {
                    $assignedUserIdList = $entity->getLinkMultipleIdList('assignedUsers');
                    if (empty($assignedUserIdList)) {
                        return false;
                    }
                } else if ($entity->hasAttribute('assignedUserId')) {
                    if (!$entity->get('assignedUserId')) {
                        return false;
                    }
                }
            }
            return true;
        }

        $newIdList = [];

        if (!$entity->isNew()) {
            $existingIdList = [];
            foreach ($entity->get('teams') as $team) {
                $existingIdList[] = $team->id;
            }
            foreach ($teamIdList as $id) {
                if (!in_array($id, $existingIdList)) {
                    $newIdList[] = $id;
                }
            }
        } else {
            $newIdList = $teamIdList;
        }

        if (empty($newIdList)) {
            return true;
        }

        $userTeamIdList = $this->getUser()->getLinkMultipleIdList('teams');

        foreach ($newIdList as $id) {
            if (!in_array($id, $userTeamIdList)) {
                return false;
            }
        }
        return true;
    }


    protected function stripTags($string)
    {
        return strip_tags($string, '<a><img><p><br><span><ol><ul><li><blockquote><pre><h1><h2><h3><h4><h5><table><tr><td><th><thead><tbody><i><b>');
    }

    protected function filterInputAttribute($attribute, $value)
    {
        if (in_array($attribute, $this->notFilteringAttributeList)) {
            return $value;
        }
        $methodName = 'filterInputAttribute' . ucfirst($attribute);
        if (method_exists($this, $methodName)) {
            $value = $this->$methodName($value);
        }
        return $value;
    }

    protected function filterInput($data)
    {
        if (!is_object($data)) {
            return;
        }

        foreach ($this->readOnlyAttributeList as $attribute) {
            unset($data->$attribute);
        }

        foreach ($data as $key => $value) {
            $data->$key = $this->filterInputAttribute($key, $data->$key);
        }

        foreach ($this->getAcl()->getScopeForbiddenAttributeList($this->entityType, 'edit') as $attribute) {
            unset($data->$attribute);
        }

        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            foreach ($data as $field => $value) {
                $fieldDefs = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $field]);
                if (empty($fieldDefs['type']) || empty($fieldDefs['multilangField'])) {
                    continue 1;
                }

                switch ($fieldDefs['type']) {
                    case 'enum':
                        $key = array_search($value, $fieldDefs['options']);
                        $data->{$fieldDefs['multilangField']} = $key === false ? null : $fieldDefs['optionsOriginal'][$key];
                        break;
                    case 'multiEnum':
                        $keys = [];
                        if (!empty($value)) {
                            foreach ($value as $item) {
                                $keys[] = array_search($item, $fieldDefs['options']);
                            }
                        }
                        $values = [];
                        foreach ($keys as $key) {
                            $values[] = $fieldDefs['optionsOriginal'][$key];
                        }
                        $data->{$fieldDefs['multilangField']} = $values;
                        break;
                }
            }
        }
    }

    protected function handleInput($data)
    {

    }

    protected function processDuplicateCheck(Entity $entity, $data)
    {
        if (empty($data->forceDuplicate)) {
            $duplicates = $this->checkEntityForDuplicate($entity, $data);
            if (!empty($duplicates)) {
                $reason = array(
                    'reason' => 'Duplicate',
                    'data' => $duplicates
                );
                throw new Conflict(json_encode($reason));
            }
        }
    }

    public function populateDefaults(Entity $entity, $data)
    {
        if (!$this->getUser()->isPortal()) {
            $forbiddenFieldList = null;
            if ($entity->hasAttribute('assignedUserId')) {
                $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit');
                if (in_array('assignedUser', $forbiddenFieldList)) {
                    $entity->set('assignedUserId', $this->getUser()->id);
                    $entity->set('assignedUserName', $this->getUser()->get('name'));
                }
            }

            if ($entity->hasLinkMultipleField('teams')) {
                if (is_null($forbiddenFieldList)) {
                    $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit');
                }
                if (in_array('teams', $forbiddenFieldList)) {
                    if ($this->getUser()->get('defaultTeamId')) {
                        $defaultTeamId = $this->getUser()->get('defaultTeamId');
                        $entity->addLinkMultipleId('teams', $defaultTeamId);
                        $teamsNames = $entity->get('teamsNames');
                        if (!$teamsNames || !is_object($teamsNames)) {
                            $teamsNames = (object) [];
                        }
                        $teamsNames->$defaultTeamId = $this->getUser()->get('defaultTeamName');
                        $entity->set('teamsNames', $teamsNames);
                    }
                }
            }
        }
    }

    public function createEntity($attachment)
    {
        $attachment = $this
            ->dispatchEvent('beforeCreateEntity', new Event(['attachment' => $attachment]))
            ->getArgument('attachment');

        if (!$this->getAcl()->check($this->getEntityType(), 'create')) {
            throw new Forbidden();
        }

        $entity = $this->getRepository()->get();

        $this->filterInput($attachment);
        $this->handleInput($attachment);

        unset($attachment->modifiedById);
        unset($attachment->modifiedByName);
        unset($attachment->modifiedAt);
        unset($attachment->createdById);
        unset($attachment->createdByName);
        unset($attachment->createdAt);

        $entity->set($attachment);

        if (!$this->getAcl()->check($entity, 'create')) {
            throw new Forbidden();
        }

        $this->populateDefaults($entity, $attachment);

        $this->beforeCreateEntity($entity, $attachment);

        // set owner user
        $this->setOwnerAndAssignedUser($entity);

        // Are all required fields filled ?
        $this->checkRequiredFields($entity, $attachment);

        // validate field with pattern
        $this->checkFieldsWithPattern($entity);

        if (!$this->checkAssignment($entity)) {
            throw new Forbidden('Assignment permission failure');
        }

        $this->processDuplicateCheck($entity, $attachment);

        if ($this->storeEntity($entity)) {
            $this->linkHierarchically($entity, $attachment);
            $this->updateRelationData($entity, $attachment);
            $this->afterCreateEntity($entity, $attachment);
            $this->afterCreateProcessDuplicating($entity, $attachment);
            $this->prepareEntityForOutput($entity);
            $this->loadPreview($entity);

            $this->processActionHistoryRecord('create', $entity);

            return $this
                ->dispatchEvent('afterCreateEntity', new Event(['attachment' => $attachment, 'entity' => $entity]))
                ->getArgument('entity');
        }

        throw new Error();
    }

    public function updateEntity($id, $data)
    {
        $event = $this
            ->dispatchEvent('beforeUpdateEntity', new Event(['id' => $id, 'data' => $data]));

        $id = $event->getArgument('id');
        $data = $event->getArgument('data');

        unset($data->deleted);

        if (empty($id)) {
            throw new BadRequest();
        }

        $this->filterInput($data);
        $this->handleInput($data);

        unset($data->modifiedById);
        unset($data->modifiedByName);
        unset($data->createdById);
        unset($data->createdByName);
        unset($data->createdAt);

        if ($this->getEntityBeforeUpdate) {
            $entity = $this->getEntity($id);
        } else {
            $entity = $this->getRepository()->get($id);
        }

        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        if ($this->getConfig()->get('checkForConflicts', true) && !empty($conflicts = $this->getFieldsThatConflict($entity, $data))) {
            throw (new Conflict(sprintf($this->getInjection('language')->translate('editedByAnotherUser', 'exceptions', 'Global'), implode(', ', $conflicts))))->setFields($conflicts);
        }

        if (!$this->isEntityUpdated($entity, $data)) {
            throw new NotModified();
        }

        $entity->set($data);

        $this->beforeUpdateEntity($entity, $data);

        // set owner user
        $this->setOwnerAndAssignedUser($entity);

        // Are all required fields filled ?
        $this->checkRequiredFields($entity, $data);

        // validate field with pattern
        $this->checkFieldsWithPattern($entity);

        if (!$this->checkAssignment($entity)) {
            throw new Forbidden();
        }

        if ($this->checkForDuplicatesInUpdate) {
            $this->processDuplicateCheck($entity, $data);
        }

        if ($this->storeEntity($entity)) {
            if ($this->isRelationPanelChanges($data)) {
                $this->updateRelationData($entity, $data);
            }

            $this->afterUpdateEntity($entity, $data);
            $this->prepareEntityForOutput($entity);
            $this->loadPreview($entity);

            $this->processActionHistoryRecord('update', $entity);

            return $this
                ->dispatchEvent('afterUpdateEntity', new Event(['id' => $id, 'data' => $data, 'entity' => $entity]))
                ->getArgument('entity');
        }

        throw new Error();
    }

    protected function isRelationPanelChanges(\stdClass $data): bool
    {
        return property_exists($data, '_relationName') && property_exists($data, '_relationEntity') && property_exists($data, '_relationEntityId');
    }

    protected function linkHierarchically(Entity $entity, \stdClass $attachment): void
    {
        if (!$this->isRelationPanelChanges($attachment) || $this->getMetadata()->get(['scopes', $attachment->_relationEntity, 'type']) !== 'Hierarchy') {
            return;
        }

        $field = $this->getMetadata()->get(['entityDefs', $attachment->_relationEntity, 'links', $attachment->_relationName, 'foreign'], '') . 'Ids';
        if (!property_exists($attachment, $field) || !is_array($attachment->$field)) {
            return;
        }

        $foreignService = $this->getServiceFactory()->create($attachment->_relationEntity);

        foreach ($attachment->$field as $foreignId) {
            $foreignService->createPseudoTransactionLinkJobs($foreignId, $attachment->_relationName, $entity->get('id'));
        }
    }

    protected function updateRelationData(Entity $entity, \stdClass $data): void
    {
        if (!$this->isRelationPanelChanges($data)) {
            return;
        }

        $linkData = $this->getMetadata()->get(['entityDefs', $data->_relationEntity, 'links', $data->_relationName]);

        if (empty($linkData['additionalColumns']) || empty($linkData['relationName']) || empty($linkData['entity'])) {
            return;
        }

        $setData = [];
        foreach ($linkData['additionalColumns'] as $field => $fieldData) {
            if (property_exists($data, $field)) {
                $setData[$field] = $data->$field;
            }
        }

        if (empty($setData)) {
            return;
        }

        $rel1 = !empty($linkData['midKeys'][1]) ? $linkData['midKeys'][1] : $data->_relationEntity . 'Id';
        $rel2 = !empty($linkData['midKeys'][0]) ? $linkData['midKeys'][0] : $entity->getEntityType() . 'Id';

        $this
            ->getRepository()
            ->updateRelationData($linkData['relationName'], $setData, $rel1, $data->_relationEntityId, $rel2, $entity->get('id'));
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $this->beforeCreate($entity, get_object_vars($data)); // TODO remove in 5.1.0

        $this->checkForSkipComplete($entity, $data);
    }

    protected function afterCreateEntity(Entity $entity, $data)
    {
        $this->afterCreate($entity, get_object_vars($data)); // TODO remove in 5.1.0
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $this->beforeUpdate($entity, get_object_vars($data)); // TODO remove in 5.1.0

        $this->checkForSkipComplete($entity, $data);
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        $this->afterUpdate($entity, get_object_vars($data)); // TODO remove in 5.1.0
    }

    protected function beforeDeleteEntity(Entity $entity)
    {
        $this->beforeDelete($entity); // TODO remove in 5.1.0

        $this->checkForSkipComplete($entity, null);
    }

    protected function afterDeleteEntity(Entity $entity)
    {
        $this->afterDelete($entity); // TODO remove in 5.1.0
    }

    /**
     * @param Entity $entity
     * @param $data
     */
    protected function checkForSkipComplete(Entity $entity, $data): void
    {
        if (!empty($data->skipComplete)) {
            $entity->skipComplete = true;
        }
    }

    /** Deprecated */
    protected function beforeCreate(Entity $entity, array $data = array())
    {
    }

    /** Deprecated */
    protected function afterCreate(Entity $entity, array $data = array())
    {
    }

    /** Deprecated */
    protected function beforeUpdate(Entity $entity, array $data = array())
    {
    }

    /** Deprecated */
    protected function afterUpdate(Entity $entity, array $data = array())
    {
    }

    /** Deprecated */
    protected function beforeDelete(Entity $entity)
    {
    }

    /** Deprecated */
    protected function afterDelete(Entity $entity)
    {
    }

    public function deleteEntity($id)
    {
        $id = $this
            ->dispatchEvent('beforeDeleteEntity', new Event(['id' => $id]))
            ->getArgument('id');

        if (empty($id)) {
            throw new BadRequest();
        }

        $entity = $this->getRepository()->get($id);

        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'delete')) {
            throw new Forbidden();
        }

        $this->beforeDeleteEntity($entity);

        $result = $this->getRepository()->remove($entity, $this->getDefaultRepositoryOptions());
        if ($result) {
            $this->afterDeleteEntity($entity);
            $this->processActionHistoryRecord('delete', $entity);
            $result = $this->dispatchEvent('afterDeleteEntity', new Event(['id' => $id, 'result' => $result]))->getArgument('result');
        }

        return $result;
    }

    protected function getSelectParams($params)
    {
        $selectParams = $this->getSelectManager($this->entityType)->getSelectParams($params, true, true);

        return $selectParams;
    }

    public function findEntities($params)
    {
        $params = $this
            ->dispatchEvent('beforeFindEntities', new Event(['params' => $params]))
            ->getArgument('params');

        $disableCount = false;
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
               $maxSize = $params['maxSize'];
               $params['maxSize'] = $params['maxSize'] + 1;
           }
        }

        $selectParams = $this->getSelectParams($params);

        $selectParams['maxTextColumnsLength'] = $this->getMaxSelectTextAttributeLength();
        $selectParams['skipCurrencyConvertedParams']
            = isset($params['skipCurrencyConvertedParams']) ? $params['skipCurrencyConvertedParams'] : false;

        $selectAttributeList = $this->getSelectAttributeList($params);
        if ($selectAttributeList) {
            $selectParams['select'] = $selectAttributeList;
        } else {
            $selectParams['skipTextColumns'] = $this->isSkipSelectTextAttributes();
        }

        $collection = $this->getRepository()->find($selectParams);

        $this->prepareCollectionForOutput($collection, $selectParams);

        foreach ($collection as $e) {
            $this->loadAdditionalFieldsForList($e);
            if (!empty($params['loadAdditionalFields'])) {
                $this->loadAdditionalFields($e);
            }
            if (!empty($selectAttributeList)) {
                $this->loadLinkMultipleFieldsForList($e, $selectAttributeList);
            }
            $this->prepareEntityForOutput($e);
        }

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

        return $this
            ->dispatchEvent('afterFindEntities', new Event(['params' => $params, 'result' => ['total' => $total, 'collection' => $collection]]))
            ->getArgument('result');
    }

    public function getListKanban($params)
    {
        $params = $this
            ->dispatchEvent('beforeGetListKanban', new Event(['params' => $params]))
            ->getArgument('params');

        $disableCount = false;
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
               $maxSize = $params['maxSize'];
               $params['maxSize'] = $params['maxSize'] + 1;
           }
        }

        $selectParams = $this->getSelectParams($params);

        $selectParams['maxTextColumnsLength'] = $this->getMaxSelectTextAttributeLength();

        $selectAttributeList = $this->getSelectAttributeList($params);
        if ($selectAttributeList) {
            $selectParams['select'] = $selectAttributeList;
        } else {
            $selectParams['skipTextColumns'] = $this->isSkipSelectTextAttributes();
        }

        $collection = new \Espo\ORM\EntityCollection([], $this->entityType);

        $statusField = $this->getMetadata()->get(['scopes', $this->entityType, 'statusField']);
        if (!$statusField) {
            throw new Error("No status field for entity type '{$this->entityType}'.");
        }

        $statusList = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $statusField, 'options']);
        if (empty($statusList)) {
            throw new Error("No options for status field for entity type '{$this->entityType}'.");
        }

        $statusIgnoreList = $this->getMetadata()->get(['scopes', $this->entityType, 'kanbanStatusIgnoreList'], []);

        $additionalData = (object) [
            'groupList' => []
        ];

        foreach ($statusList as $status) {
            if (in_array($status, $statusIgnoreList)) continue;
            if (!$status) continue;

            $selectParamsSub = $selectParams;
            $selectParamsSub['whereClause'][] = [
                $statusField => $status
            ];

            $o = (object) [
                'name' => $status
            ];

            $collectionSub = $this->getRepository()->find($selectParamsSub);

            if (!$disableCount) {
                $totalSub = $this->getRepository()->count($selectParamsSub);
            } else {
                if ($maxSize && count($collectionSub) > $maxSize) {
                    $totalSub = -1;
                    unset($collectionSub[count($collectionSub) - 1]);
                } else {
                    $totalSub = -2;
                }
            }

            $this->prepareCollectionForOutput($collectionSub, $selectParamsSub);

            foreach ($collectionSub as $e) {
                $this->loadAdditionalFieldsForList($e);
                if (!empty($params['loadAdditionalFields'])) {
                    $this->loadAdditionalFields($e);
                }
                if (!empty($selectAttributeList)) {
                    $this->loadLinkMultipleFieldsForList($e, $selectAttributeList);
                }
                $this->prepareEntityForOutput($e);

                $collection[] = $e;
            }

            $o->total = $totalSub;
            $o->list = $collectionSub->getValueMapList();

            $additionalData->groupList[] = $o;
        }

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

        return $this
            ->dispatchEvent('afterGetListKanban', new Event(['params' => $params, 'result' => (object) ['total' => $total,'collection' => $collection,'additionalData' => $additionalData]]))
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

    public function findLinkedEntities($id, $link, $params)
    {
        $event = $this
            ->dispatchEvent('beforeFindLinkedEntities', new Event(['id' => $id, 'link' => $link, 'params' => $params]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');
        $params = $event->getArgument('params');

        $this->getPseudoTransactionManager()->runForEntity($this->getEntityType(), $id);

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
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

        $foreignEntityName = $entity->relations[$link]['entity'];

        if (!$this->getAcl()->check($foreignEntityName, 'read')) {
            throw new Forbidden();
        }

        $recordService = $this->getRecordService($foreignEntityName);

        $disableCount = false;
        if (
            in_array($this->entityType, $this->getConfig()->get('disabledCountQueryEntityList', []))
        ) {
            $disableCount = true;
        }

        $maxSize = 0;
        if ($disableCount) {
            if (!empty($params['maxSize'])) {
                $maxSize = $params['maxSize'];
                $params['maxSize'] = $params['maxSize'] + 1;
            }
        }

        $selectParams = $this->getSelectManager($foreignEntityName)->getSelectParams($params, true);
        $selectParams['collectionOnly'] = true;

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

        $total = 0;
        $collection = $this->getRepository()->findRelated($entity, $link, $selectParams);

        if (!empty($collection) && count($collection) > 0) {
            $recordService->prepareCollectionForOutput($collection, $selectParams);
            foreach ($collection as $e) {
                $recordService->loadAdditionalFieldsForList($e);
                if (!empty($params['loadAdditionalFields'])) {
                    $recordService->loadAdditionalFields($e);
                }
                if (!empty($selectAttributeList)) {
                    $this->loadLinkMultipleFieldsForList($e, $selectAttributeList);
                }
                $recordService->prepareEntityForOutput($e);
            }

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

        return $this
            ->dispatchEvent('afterFindLinkedEntities', new Event(['id' => $id, 'link' => $link, 'params' => $params, 'result' => ['total' => $total,'collection' => $collection]]))
            ->getArgument('result');
    }

    public function linkEntity($id, $link, $foreignId)
    {
        /**
         * Delegate to Update if ManyToOne or OneToOne relation
         */
        if ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'type']) === 'belongsTo') {
            $data = new \stdClass();
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
            $data = new \stdClass();
            $data->{"{$linkData['foreign']}Id"} = $id;
            try {
                $this->getServiceFactory()->create($linkData['entity'])->updateEntity($foreignId, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        $event = $this
            ->dispatchEvent('beforeLinkEntity', new Event(['id' => $id, 'link' => $link, 'foreignId' => $foreignId]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');
        $foreignId = $event->getArgument('foreignId');

        if (empty($id) || empty($link) || empty($foreignId)) {
            throw new BadRequest;
        }

        if (in_array($link, $this->readOnlyLinkList)) {
            throw new Forbidden();
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

        $accessActionRequired = 'edit';
        if (in_array($link, $this->noEditAccessRequiredLinkList)) {
            $accessActionRequired = 'read';
        }
        if (!$this->getAcl()->check($foreignEntity, $accessActionRequired)) {
            throw new Forbidden();
        }

        $this->getRepository()->relate($entity, $link, $foreignEntity, null, $this->getDefaultRepositoryOptions());

        return $this
            ->dispatchEvent('afterLinkEntity', new Event(['id' => $id, 'entity' => $entity, 'link' => $link, 'foreignEntity' => $foreignEntity, 'result' => true]))
            ->getArgument('result');
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        /**
         * Delegate to Update if ManyToOne or OneToOne relation
         */
        if ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'type']) === 'belongsTo') {
            $data = new \stdClass();
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
            $data = new \stdClass();
            $data->{"{$linkData['foreign']}Id"} = null;
            try {
                $this->getServiceFactory()->create($linkData['entity'])->updateEntity($foreignId, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        $event = $this
            ->dispatchEvent('beforeUnlinkEntity', new Event(['id' => $id, 'link' => $link, 'foreignId' => $foreignId]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');
        $foreignId = $event->getArgument('foreignId');

        if (empty($id) || empty($link) || empty($foreignId)) {
            throw new BadRequest;
        }

        if (in_array($link, $this->readOnlyLinkList)) {
            throw new Forbidden();
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

        $accessActionRequired = 'edit';
        if (in_array($link, $this->noEditAccessRequiredLinkList)) {
            $accessActionRequired = 'read';
        }
        if (!$this->getAcl()->check($foreignEntity, $accessActionRequired)) {
            throw new Forbidden();
        }

        $this->getRepository()->unrelate($entity, $link, $foreignEntity, $this->getDefaultRepositoryOptions());

        return $this
            ->dispatchEvent('afterUnlinkEntity', new Event(['id' => $id, 'link' => $link, 'foreignEntity' => $foreignEntity, 'result' => true]))
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
        $event = $this->dispatchEvent('beforeLinkEntityMass', new Event(['id' => $id, 'link' => $link, 'where' => $where]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');
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

        $accessActionRequired = 'edit';
        if (in_array($link, $this->noEditAccessRequiredLinkList)) {
            $accessActionRequired = 'read';
        }

        if (!$this->getAcl()->check($foreignEntityType, $accessActionRequired)) {
            throw new Forbidden();
        }

        if (empty($where) || !is_array($where)) {
            $where = [];
        }

        $selectParams = $this->getRecordService($foreignEntityType)->getSelectParams(['where' => $where]);
        $this->getEntityManager()->getRepository($foreignEntityType)->handleSelectParams($selectParams);

        $query = $this
            ->getEntityManager()
            ->getQuery()
            ->createSelectQuery($foreignEntityType, array_merge($selectParams, ['select' => ['id']]));

        $foreignIds = $this
            ->getEntityManager()
            ->getPDO()
            ->query($query)
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($foreignIds as $k => $foreignId) {
            if ($k < $this->maxMassLinkCount) {
                $this->linkEntity($id, $link, $foreignId);
            } else {
                $this->getPseudoTransactionManager()->pushLinkEntityJob($this->entityType, $id, $link, $foreignId);
            }
        }

        return $this->dispatchEvent('afterLinkEntityMass', new Event(['entity' => $entity, 'link' => $link, 'result' => true]))->getArgument('result');
    }

    public function unlinkAll(string $id, string $link): bool
    {
        $event = $this->dispatchEvent('beforeUnlinkAll', new Event(['id' => $id, 'link' => $link]));

        $id = $event->getArgument('id');
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

        if (!$this->getAcl()->check($foreignEntityType, in_array($link, $this->noEditAccessRequiredLinkList) ? 'read' : 'edit')) {
            throw new Forbidden();
        }

        $foreignIds = $entity->getLinkMultipleIdList($link);

        foreach ($foreignIds as $k => $foreignId) {
            if ($k < $this->maxMassUnlinkCount) {
                $this->originUnlinkAction = true;
                $this->unlinkEntity($id, $link, $foreignId);
                $this->originUnlinkAction = false;
            } else {
                $this->getPseudoTransactionManager()->pushUnLinkEntityJob($this->entityType, $id, $link, $foreignId);
            }
        }

        return $this->dispatchEvent('afterUnlinkAll', new Event(['entity' => $entity, 'link' => $link, 'result' => true]))->getArgument('result');
    }

    public function massUpdate($data, array $params)
    {
        $event = $this->dispatchEvent('beforeMassUpdate', new Event(['data' => $data, 'params' => $params]));

        $data = $event->getArgument('data');
        $params = $event->getArgument('params');

        $this->filterInput($data);

        $ids = [];
        if (array_key_exists('ids', $params) && is_array($params['ids'])) {
            $ids = $params['ids'];
        }

        if (array_key_exists('where', $params)) {
            $selectParams = $this->getSelectParams(['where' => $params['where']]);
            $this->getEntityManager()->getRepository($this->getEntityType())->handleSelectParams($selectParams);

            $query = $this
                ->getEntityManager()
                ->getQuery()
                ->createSelectQuery($this->getEntityType(), array_merge($selectParams, ['select' => ['id']]));

            $ids = $this
                ->getEntityManager()
                ->getPDO()
                ->query($query)
                ->fetchAll(\PDO::FETCH_COLUMN);
        }

        foreach ($ids as $k => $id) {
            if ($k < $this->maxMassUpdateCount) {
                try {
                    $this->updateEntity($id, $data);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error("Update $this->entityType '$id' failed: {$e->getMessage()}");
                }
            } else {
                $this->getPseudoTransactionManager()->pushUpdateEntityJob($this->entityType, $id, $data);
            }
        }

        return $this
            ->dispatchEvent('afterMassUpdate', new Event(['data' => $data, 'result' => ['count' => count($ids), 'ids' => $ids]]))
            ->getArgument('result');
    }

    public function massRemove(array $params)
    {
        $params = $this
            ->dispatchEvent('beforeMassRemove', new Event(['params' => $params]))
            ->getArgument('params');

        $name = $this->getInjection('language')->translate('remove', 'massActions', 'Global') . ': ' . $this->entityType;

        $data = [
            'entityType' => $this->entityType
        ];
        if (array_key_exists('ids', $params) && !empty($params['ids']) && is_array($params['ids'])) {
            $data['ids'] = $params['ids'];
        }
        if (array_key_exists('where', $params)) {
            $data['where'] = $params['where'];
        }

        $this
            ->getInjection('queueManager')
            ->push($name, 'MassDelete', $data, 'High');

        return true;
    }

    public function follow($id, $userId = null)
    {
        $event = $this->dispatchEvent('beforeFollow', new Event(['id' => $id, 'userId' => $userId]));

        $id = $event->getArgument('id');
        $userId = $event->getArgument('userId');

        $entity = $this->getRepository()->get($id);

        if (!$this->getAcl()->check($entity, 'stream')) {
            throw new Forbidden();
        }

        if (empty($userId)) {
            $userId = $this->getUser()->id;
        }

        return $this
            ->dispatchEvent('afterFollow', new Event(['entity' => $entity, 'userId' => $userId, 'result' => $this->getStreamService()->followEntity($entity, $userId)]))
            ->getArgument('result');
    }

    public function unfollow($id, $userId = null)
    {
        $event = $this->dispatchEvent('beforeUnfollow', new Event(['id' => $id, 'userId' => $userId]));

        $id = $event->getArgument('id');
        $userId = $event->getArgument('userId');

        $entity = $this->getRepository()->get($id);

        if (!$this->getAcl()->check($entity, 'read')) {
            throw new Forbidden();
        }

        if (empty($userId)) {
            $userId = $this->getUser()->id;
        }

        return $this
            ->dispatchEvent('afterUnfollow', new Event(['entity' => $entity, 'userId' => $userId, 'result' => $this->getStreamService()->unfollowEntity($entity, $userId)]))
            ->getArgument('result');
    }

    public function massFollow(array $params, $userId = null)
    {
        $event = $this->dispatchEvent('beforeMassFollow', new Event(['params' => $params, 'userId' => $userId]));

        $params = $event->getArgument('params');
        $userId = $event->getArgument('userId');

        $resultIdList = [];

        if (empty($userId)) {
            $userId = $this->getUser()->id;
        }

        $streamService = $this->getStreamService();

        $ids = [];
        if (array_key_exists('ids', $params) && is_array($params['ids'])) {
            $ids = $params['ids'];
        }

        if (array_key_exists('where', $params)) {
            $selectParams = $this->getSelectParams(['where' => $params['where']]);
            $this->getEntityManager()->getRepository($this->getEntityType())->handleSelectParams($selectParams);

            $query = $this
                ->getEntityManager()
                ->getQuery()
                ->createSelectQuery($this->getEntityType(), array_merge($selectParams, ['select' => ['id']]));

            $ids = $this
                ->getEntityManager()
                ->getPDO()
                ->query($query)
                ->fetchAll(\PDO::FETCH_COLUMN);

        }

        foreach ($ids as $id) {
            $entity = $this->getEntity($id);
            if ($entity && $this->getAcl()->check($entity, 'stream')) {
                if ($streamService->followEntity($entity, $userId)) {
                    $resultIdList[] = $entity->id;
                }
            }
        }


        return $this
            ->dispatchEvent('afterMassFollow', new Event(['params' => $params, 'userId' => $userId, 'result' => ['ids' => $resultIdList, 'count' => count($resultIdList)]]))
            ->getArgument('result');
    }

    public function massUnfollow(array $params, $userId = null)
    {
        $event = $this->dispatchEvent('beforeMassUnfollow', new Event(['params' => $params, 'userId' => $userId]));

        $params = $event->getArgument('params');
        $userId = $event->getArgument('userId');

        $resultIdList = [];

        if (empty($userId)) {
            $userId = $this->getUser()->id;
        }

        $ids = [];
        if (array_key_exists('ids', $params) && is_array($params['ids'])) {
            $ids = $params['ids'];
        }

        if (array_key_exists('where', $params)) {
            $selectParams = $this->getSelectParams(['where' => $params['where']]);
            $this->getEntityManager()->getRepository($this->getEntityType())->handleSelectParams($selectParams);

            $query = $this
                ->getEntityManager()
                ->getQuery()
                ->createSelectQuery($this->getEntityType(), array_merge($selectParams, ['select' => ['id']]));

            $ids = $this
                ->getEntityManager()
                ->getPDO()
                ->query($query)
                ->fetchAll(\PDO::FETCH_COLUMN);
        }

        $streamService = $this->getStreamService();

        foreach ($ids as $id) {
            $entity = $this->getEntity($id);
            if ($entity && $this->getAcl()->check($entity, 'stream')) {
                if ($streamService->unfollowEntity($entity, $userId)) {
                    $resultIdList[] = $entity->id;
                }
            }
        }


        return $this
            ->dispatchEvent('afterMassUnfollow', new Event(['params' => $params, 'userId' => $userId, 'result' => ['ids' => $resultIdList, 'count' => count($resultIdList)]]))
            ->getArgument('result');
    }

    protected function getDuplicateWhereClause(Entity $entity, $data)
    {
        return false;
    }

    public function checkEntityForDuplicate(Entity $entity, $data = null)
    {
        if (!$data) {
            $data = (object) [];
        }

        $where = $this->getDuplicateWhereClause($entity, $data);

        if ($where) {
            if ($entity->id) {
                $where['id!='] = $entity->id;
            }
            $duplicateList = $this->getRepository()->where($where)->find();
            if (count($duplicateList)) {
                $result = array();
                foreach ($duplicateList as $e) {
                    $result[$e->id] = $e->getValues();
                }
                return $result;
            }
        }
        return false;
    }

    protected function getLocaleId(): string
    {
        $localeId = $this->getConfig()->get('localeId');
        if (!empty($this->getInjection('preferences')->get('locale'))) {
            $localeId = $this->getInjection('preferences')->get('locale');
        }

        return $localeId;
    }

    protected function prepareUnitFieldValue(Entity $entity, string $fieldName, string $measure): void
    {
        if (empty($value = $entity->get($fieldName)) || empty($unitName = (string)$entity->get($fieldName . 'Unit'))) {
            return;
        }

        $unitsOfMeasure = Json::decode(Json::encode($this->getConfig()->get('unitsOfMeasure', [])), true);

        if (!isset($unitsOfMeasure[$measure]['unitListData'])) {
            return;
        }

        $unitListData = $unitsOfMeasure[$measure]['unitListData'];

        foreach ($unitListData as $row) {
            if ($row['name'] === $unitName) {
                $unitData = $row;
                break;
            }
        }

        if (empty($unitData)) {
            return;
        }

        $allUnits = [];
        foreach ($unitListData as $row) {
            $allUnits[$row['name']] = round($value / $unitData['multiplier'] * $row['multiplier'], 4);
        }

        $entity->set($fieldName . 'AllUnits', $allUnits);

        $locales = $this->getConfig()->get('locales', []);

        $localeId = $this->getLocaleId();

        $localedUnitsIds = [];
        $localedUnitDefaultId = null;

        if (isset($locales[$localeId]['measures'])) {
            foreach ($locales[$localeId]['measures'] as $row) {
                if ($row['name'] === $measure) {
                    $localedUnitsIds = $row['units'];
                    $localedUnitDefaultId = $row['defaultUnit'];
                }
            }
        }

        if (empty($localedUnitsIds)) {
            return;
        }

        if (in_array($unitData['id'], $localedUnitsIds)) {
            return;
        }

        if (!empty($unitData['convertToId']) && in_array($unitData['convertToId'], $localedUnitsIds)) {
            $convertTo = $unitListData[$unitData['convertToId']];
        } else {
            $convertTo = $unitListData[$localedUnitDefaultId];
        }

        $entity->set($fieldName, $allUnits[$convertTo['name']]);
        $entity->set($fieldName . 'Unit', $convertTo['name']);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        foreach ($this->internalAttributeList as $field) {
            $entity->clear($field);
        }
        foreach ($this->getAcl()->getScopeForbiddenAttributeList($entity->getEntityType(), 'read') as $attribute) {
            $entity->clear($attribute);
        }

        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []) as $name => $defs) {
            if (empty($defs['type'])) {
                continue 1;
            }

            switch ($defs['type']) {
                case 'unit':
                    if ($entity->has($name) && !empty($defs['measure'])) {
                        $this->prepareUnitFieldValue($entity, $name, $defs['measure']);
                    }
                    break;
                case 'enum':
                    if (!empty($defs['multilangField'])) {
                        $value = $entity->get($defs['multilangField']);
                        $key = array_search($value, $defs['optionsOriginal']);
                        if ($key !== false) {
                            $value = $defs['options'][$key];
                        }
                        $entity->set($name, $value);
                    }
                    break;
                case 'multiEnum':
                    if (!empty($defs['multilangField'])) {
                        $value = $entity->get($defs['multilangField']);
                        if (!empty($value)) {
                            $newValue = [];
                            foreach ($value as $v) {
                                $key = array_search($v, $defs['optionsOriginal']);
                                if ($key !== false) {
                                    $newValue[] = $defs['options'][$key];
                                }
                            }
                            $value = $newValue;
                        }
                        $entity->set($name, $value);
                    }
                    break;
            }
        }

        // modify entity if header language exist
        if (!empty($language = $this->getHeaderLanguage())) {
            foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], []) as $fieldName => $fieldData) {
                if (!empty($fieldData['isMultilang'])) {
                    $langField = $fieldName . ucfirst(Util::toCamelCase(strtolower($language)));
                    $entity->set($fieldName, $entity->get($langField));
                }
            }

            foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], []) as $fieldName => $fieldData) {
                if (!empty($fieldData['multilangLocale'])) {
                    $entity->clear($fieldName);
                }
            }
        }

        $this->dispatchEvent('prepareEntityForOutput', new Event(['entity' => $entity]));
    }

    public function merge($id, array $sourceIdList = array(), $attributes)
    {
        if (empty($id)) {
            throw new Error();
        }

        $repository = $this->getRepository();

        $entity = $this->getEntityManager()->getEntity($this->getEntityType(), $id);

        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $this->filterInput($attributes);

        $entity->set($attributes);
        if (!$this->checkAssignment($entity)) {
            throw new Forbidden();
        }

        $sourceList = array();
        foreach ($sourceIdList as $sourceId) {
            $source = $this->getEntity($sourceId);
            $sourceList[] = $source;
            if (!$this->getAcl()->check($source, 'edit') || !$this->getAcl()->check($source, 'delete')) {
                throw new Forbidden();
            }
        }

        $this->beforeMerge($entity, $sourceList, $attributes);

        $fieldDefs = $this->getMetadata()->get('entityDefs.' . $entity->getEntityType() . '.fields', array());

        $pdo = $this->getEntityManager()->getPDO();

        foreach ($sourceList as $source) {
            $sql = "
                UPDATE `note`
                    SET
                        `parent_id` = " . $pdo->quote($entity->id) . ",
                        `parent_type` = " . $pdo->quote($entity->getEntityType()) . "
                WHERE
                    `type` IN ('Post', 'EmailSent', 'EmailReceived') AND
                    `parent_id` = " . $pdo->quote($source->id) . " AND
                    `parent_type` = ".$pdo->quote($source->getEntityType())." AND
                    `deleted` = 0
            ";
            $pdo->query($sql);
        }

        $mergeLinkList = [];
        $linksDefs = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links']);
        foreach ($linksDefs as $link => $d) {
            if (!empty($d['notMergeable'])) {
                continue;
            }
            if (!empty($d['type']) && in_array($d['type'], ['hasMany', 'hasChildren'])) {
                $mergeLinkList[] = $link;
            }
        }

        foreach ($sourceList as $source) {
            foreach ($mergeLinkList as $link) {
                $linkedList = $repository->findRelated($source, $link);
                foreach ($linkedList as $linked) {
                    $repository->relate($entity, $link, $linked);
                }
            }
        }

        foreach ($sourceList as $source) {
            $this->getEntityManager()->removeEntity($source);
        }

        $entity->set($attributes);
        $repository->save($entity);

        $this->afterMerge($entity, $sourceList, $attributes);

        return true;
    }

    protected function beforeMerge(Entity $entity, array $sourceList, $attributes)
    {
    }

    protected function afterMerge(Entity $entity, array $sourceList, $attributes)
    {
    }

    protected function findLinkedEntitiesFollowers($id, $params)
    {
        $maxSize = 0;

        $entity = $this->getEntityManager()->getEntity($this->entityType, $id);
        if (!$entity) {
            throw new NotFound();
        }

        $data = $this->getStreamService()->getEntityFollowers($entity, $params['offset'], $params['maxSize']);

        $list = [];

        foreach ($data['idList'] as $id) {
            $list[] = array(
                'id' => $id,
                'name' => $data['nameMap']->$id
            );
        }

        if ($maxSize && count($list) > $maxSize) {
            $total = -1;
            unset($list[count($list) - 1]);
        } else {
            $total = -2;
        }

        return array(
            'total' => $total,
            'list' => $list
        );
    }

    public function getDuplicateAttributes($id)
    {
        if (empty($id)) {
            throw new BadRequest();
        }

        $entity = $this->getEntity($id);

        if (!$entity) {
            throw new NotFound();
        }

        $attributes = $entity->getValueMap();
        unset($attributes->id);

        $fields = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], array());

        $fieldManager = new \Espo\Core\Utils\FieldManagerUtil($this->getMetadata());

        foreach ($fields as $field => $item) {
            if (empty($item['type'])) continue;
            $type = $item['type'];

            if (!empty($item['duplicateIgnore'])) {
                $attributeToIgnoreList = $fieldManager->getAttributeList($this->entityType, $field);
                foreach ($attributeToIgnoreList as $attribute) {
                    unset($attributes->$attribute);
                }
                continue;
            }

            if (in_array($type, ['file', 'image'])) {
                $attachment = $entity->get($field);
                if ($attachment) {
                    $attachment = $this->getEntityManager()->getRepository('Attachment')->getCopiedAttachment($attachment);
                    $idAttribute = $field . 'Id';
                    if ($attachment) {
                        $attributes->$idAttribute = $attachment->id;
                    }
                }
            } else if (in_array($type, ['attachmentMultiple'])) {
                $attachmentList = $entity->get($field);
                if (count($attachmentList)) {
                    $idList = [];
                    $nameHash = (object) [];
                    $typeHash = (object) [];
                    foreach ($attachmentList as $attachment) {
                        $attachment = $this->getEntityManager()->getRepository('Attachment')->getCopiedAttachment($attachment);
                        if ($attachment) {
                            $idList[] = $attachment->id;
                            $nameHash->{$attachment->id} = $attachment->get('name');
                            $typeHash->{$attachment->id} = $attachment->get('type');
                        }
                    }
                    $attributes->{$field . 'Ids'} = $idList;
                    $attributes->{$field . 'Names'} = $nameHash;
                    $attributes->{$field . 'Types'} = $typeHash;
                }
            } else if ($type === 'linkMultiple') {
                $foreignLink = $entity->getRelationParam($field, 'foreign');
                $foreignEntityType = $entity->getRelationParam($field, 'entity');
                if ($foreignEntityType && $foreignLink) {
                    $foreignRelationType = $this->getMetadata()->get(['entityDefs', $foreignEntityType, 'links', $foreignLink, 'type']);
                    if ($foreignRelationType !== 'hasMany') {
                        unset($attributes->{$field . 'Ids'});
                        unset($attributes->{$field . 'Names'});
                        unset($attributes->{$field . 'Columns'});
                    }
                }
            }
        }

        $attributes->_duplicatingEntityId = $id;

        return $attributes;
    }

    protected function afterCreateProcessDuplicating(Entity $entity, $data)
    {
        if (!isset($data->_duplicatingEntityId)) return;

        $duplicatingEntityId = $data->_duplicatingEntityId;
        if (!$duplicatingEntityId) return;
        $duplicatingEntity = $this->getEntityManager()->getEntity($entity->getEntityType(), $duplicatingEntityId);
        if (!$duplicatingEntity) return;
        if (!$this->getAcl()->check($duplicatingEntity, 'read')) return;

        $this->duplicateLinks($entity, $duplicatingEntity);
    }

    /**
     * @param Entity $entity
     * @param Entity $duplicatingEntity
     */
    protected function duplicateLinks(Entity $entity, Entity $duplicatingEntity)
    {
        // get all links
        $allLinks = $this->getEntityLinks($entity);

        // prepare links
        foreach ($allLinks as $field => $row) {
            if (!empty($row['type']) && $row['type'] == 'hasMany') {
                $links[] = $field;
            }
        }

        if (!empty($links)) {
            foreach ($links as $link) {
                // prepare method name
                $methodName = 'duplicate' . ucfirst($link);

                /** @var Record $handler */
                $handler = $this
                    ->getInjection('eventManager')
                    ->dispatch(
                        $entity->getEntityType() . 'Service', 'beforeDuplicateLink',
                        new Event(
                            [
                                'handler'           => clone $this,
                                'entity'            => $entity,
                                'duplicatingEntity' => $duplicatingEntity,
                                'link'              => $link
                            ]
                        )
                    )
                    ->getArgument('handler');

                // call custom method
                if (method_exists($handler, $methodName)) {
                    try {
                        $handler->{$methodName}($entity, $duplicatingEntity);
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error($e->getMessage());
                    }
                    continue 1;
                }

                /**
                 * Duplicate Relationship entity
                 */
                $foreignEntity = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'entity']);
                if (!empty($foreignEntity)) {
                    $foreignEntityType = $this->getMetadata()->get(['scopes', $foreignEntity, 'type']);
                    $foreign = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'foreign']);
                    if ($foreignEntityType === 'Relationship' && !empty($foreign)) {
                        foreach ($duplicatingEntity->get($link) as $item) {
                            $record = $this->getEntityManager()->getEntity($foreignEntity);
                            $record->set($item->toArray());
                            $record->id = null;
                            $record->clear('createdAt');
                            $record->clear('modifiedAt');
                            $record->clear('createdById');
                            $record->clear('modifiedById');
                            $record->set($foreign . 'Id', $entity->get('id'));
                            $record->set($foreign . 'Name', $entity->get('name'));
                            $this->getEntityManager()->saveEntity($record);
                        }
                        continue 1;
                    }
                }

                if (!empty($allLinks[$link]['relationName'])) {
                    $data = $duplicatingEntity->get($link);
                    if (count($data) > 0) {
                        foreach ($data as $item) {
                            try {
                                $this->getEntityManager()->getRepository($entity->getEntityType())->relate($entity, $link, $item);
                            } catch (\Throwable $e) {
                                $GLOBALS['log']->error($e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }

    protected function getFieldByTypeList($type)
    {
        return $this->getFieldManagerUtil()->getFieldByTypeList($this->entityType, $type);
    }

    public function getSelectAttributeList($params)
    {
        if ($this->forceSelectAllAttributes) {
            return null;
        }

        if ($this->selectAttributeList) {
            return $this->selectAttributeList;
        }

        $seed = $this->getEntityManager()->getEntity($this->getEntityType());

        if (array_key_exists('select', $params)) {
            $passedAttributeList = $params['select'];
        } else {
            $passedAttributeList = null;
        }

        if ($passedAttributeList) {
            $attributeList = [];
            if (!in_array('id', $passedAttributeList)) {
                $attributeList[] = 'id';
            }
            $aclAttributeList = ['assignedUserId', 'createdById'];

            if ($this->getUser()->isPortal()) {
                $aclAttributeList[] = 'accountId';
            }

            foreach ($aclAttributeList as $attribute) {
                if (!in_array($attribute, $passedAttributeList) && $seed->hasAttribute($attribute)) {
                    $attributeList[] = $attribute;
                }
            }

            foreach ($passedAttributeList as $attribute) {
                if (!in_array($attribute, $attributeList) && $seed->hasAttribute($attribute)) {
                    $attributeList[] = $attribute;
                    $mainField = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $attribute, 'multilangField']);
                    if (!empty($mainField) && !in_array($mainField, $attributeList)) {
                        $attributeList[] = $mainField;
                    }
                }
            }

            if (!empty($params['sortBy'])) {
                $sortByField = $params['sortBy'];
                $sortByFieldType = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields', $sortByField, 'type']);

                if ($sortByFieldType === 'currency') {
                    if (!in_array($sortByField . 'Converted', $attributeList)) {
                        $attributeList[] = $sortByField . 'Converted';
                    }
                }

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

            if (!empty($language = $this->getHeaderLanguage())) {
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
     * @param Entity $entity
     *
     * @return array
     */
    protected function getEntityLinks(Entity $entity): array
    {
        return $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links'], []);
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $event = $this->dispatchEvent('beforeCheckingIsEntityUpdated', new Event(['entity' => $entity, 'data' => $data]));

        $entity = $event->getArgument('entity');
        $data = $event->getArgument('data');

        $skip = [
            'id',
            'deleted',
            'createdAt',
            'modifiedAt',
            'createdById'
        ];

        $linkNames = [];
        $linkMultipleIds = [];
        $linkMultipleNames = [];
        $linkMultipleAddOnlyMode = [];
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields']) as $name => $fieldData) {
            if (isset($fieldData['type'])) {
                if ($fieldData['type'] === 'link' || $fieldData['type'] === 'asset') {
                    $linkNames[] = $name . 'Name';
                }
                if ($fieldData['type'] === 'linkMultiple') {
                    $linkMultipleIds[] = $name . 'Ids';
                    $linkMultipleNames[] = $name . 'Names';
                    $linkMultipleAddOnlyMode[$name . 'Ids'] = $name . 'AddOnlyMode';
                }
            }
        }

        foreach ($entity->getFields() as $field => $params) {
            if (in_array($field, $skip)) {
                continue 1;
            }

            if (!property_exists($data, $field)) {
                continue 1;
            }

            if (in_array($field, $linkNames)) {
                continue 1;
            }

            if (in_array($field, $linkMultipleNames)) {
                continue 1;
            }

            if (in_array($field, $linkMultipleAddOnlyMode)) {
                continue 1;
            }

            if (!isset($params['type'])) {
                continue 1;
            }

            if (in_array($field, $linkMultipleIds)) {
                $collection = $entity->get(substr($field, 0, -3));
                $value = (!empty($collection) && count($collection) > 0) ? array_column($collection->toArray(), 'id') : [];
                $value = array_unique($value);
                sort($value);
                if (is_array($data->$field)) {
                    $data->$field = array_unique($data->$field);
                    sort($data->$field);

                    if (property_exists($data, $linkMultipleAddOnlyMode[$field]) && !empty($data->{$linkMultipleAddOnlyMode[$field]})) {
                        $preparedValue = [];
                        foreach ($data->$field as $v) {
                            if (in_array($v, $value)) {
                                $preparedValue[] = $v;
                            }
                        }
                        $value = $preparedValue;
                    }
                }
            } else {
                $value = $entity->get($field);
            }

            if ($params['type'] === 'bool' && !empty($data->$field) !== !empty($value)) {
                return true;
            }

            // strict type for NULL
            if (($data->$field === null && $value !== null) || ($data->$field !== null && $value === null)) {
                return true;
            }

            if (!$this->areValuesEqual($entity, $field, $data->$field, $value)) {
                return true;
            }
        }

        return false;
    }

    protected function areValuesEqual(Entity $entity, string $field, $value1, $value2): bool
    {
        if (!isset($entity->getFields()[$field]['type'])) {
            return false;
        }

        $type = $entity->getFields()[$field]['type'];

        if (in_array($type, [Entity::JSON_ARRAY, Entity::JSON_OBJECT])) {
            if (is_string($value1)) {
                $value1 = Json::decode($value1, true);
            }
            if (is_string($value2)) {
                $value2 = Json::decode($value2, true);
            }
        }

        return Entity::areValuesEqual($type, $value1, $value2);
    }

    /**
     * @param Entity    $entity
     * @param \stdClass $data
     *
     * @return array
     */
    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        // prepare data
        $data = json_decode(json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_NUMERIC_CHECK), true);

        if (empty($data['_prev'])) {
            return [];
        }

        $prev = $data['_prev'];

        unset($data['_prev']);
        unset($data['_silentMode']);

        $fieldsThatConflict = [];
        foreach ($data as $field => $newValue) {
            if ($field == 'data' || $this->hasSuffix($field, 'Name') || $this->hasSuffix($field, 'Names') || mb_substr($field, 0, 8) === 'complete') {
                continue 1;
            }

            // for link multiple
            if ($this->hasSuffix($field, 'Ids') && !empty($collection = $entity->get($this->removeSuffix($field, 'Ids'))) && $collection instanceof EntityCollection) {
                $entity->set($field, array_column($collection->toArray(), 'id'));
            }

            $fieldMetadata = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields', $field], []);

            // prepare unit field
            if (!empty($fieldMetadata['type']) && $fieldMetadata['type'] === 'unit' && !empty($fieldMetadata['measure'])) {
                $this->prepareUnitFieldValue($entity, $field, $fieldMetadata['measure']);
            }

            if ($entity->has($field) && Util::toMd5($entity->get($field)) != Util::toMd5($prev[$field])) {
                foreach (['Id', 'Ids', 'Currency', 'Unit'] as $suffix) {
                    $name = $this->removeSuffix($field, $suffix);
                    $type = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields', $name, 'type'], '');

                    if (!empty($type) && in_array($type, ['link', 'linkMultiple', 'currency', 'unit'])) {
                        $field = $name;
                    }

                }
                $fieldsThatConflict[$field] = $this->getInjection('language')->translate($field, 'fields', $this->entityName);
            }
        }

        return $fieldsThatConflict;
    }

    /**
     * @param string $str
     * @param string $suffix
     *
     * @return bool
     */
    protected function hasSuffix(string $str, string $suffix): bool
    {
        return strpos($str, $suffix) !== false && substr($str, strlen($suffix) * -1) === $suffix;
    }

    /**
     * @param string $str
     * @param string $suffix
     *
     * @return string
     */
    protected function removeSuffix(string $str, string $suffix): string
    {
        if ($this->hasSuffix($str, $suffix)) {
            $str = mb_substr($str, 0, strlen($suffix) * -1);
        }

        return $str;
    }

    /**
     * @param IEntity $entity
     */
    private function setOwnerAndAssignedUser(IEntity $entity): void
    {
        // has owner param
        $hasOwner = !empty($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'hasOwner']));

        if (($hasOwner || $entity->hasAttribute('ownerUserId')) && empty($entity->get('ownerUserId'))) {
            $entity->set('ownerUserId', $this->getEntityManager()->getUser()->id);
        }

        // has assigned
        $hasAssigned = !empty($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'hasAssignedUser']));

        if (($hasAssigned || $entity->hasAttribute('assignedUserId')) && empty($entity->get('assignedUserId'))) {
            $entity->set('assignedUserId', $this->getEntityManager()->getUser()->id);
        }
    }

    /**
     * @param string $field
     * @param Entity $entity
     * @param $typeResult
     *
     * @return bool
     * @throws Error
     */
    public function isRequiredField(string $field, Entity $entity, $typeResult): bool
    {
        if ($this->relationFields === false) {
            $this->setRelationFields($entity);
        }
        if (isset($this->relationFields[$field])) {
            $field = $this->relationFields[$field];
        }

        $result = false;

        $item = $this->getMetadata()->get("clientDefs.{$entity->getEntityName()}.dynamicLogic.fields.$field.$typeResult.conditionGroup", []);

        if (empty($item)) {
            $fields = $entity->getFields();
            if (!empty($fields[$field]['relation'])) {
                $relation = $fields[$field]['relation'];
                if (empty($this->relationFields['usedRelation'][$relation])) {
                    $this->relationFields['usedRelation'][$relation] = $relation;
                    $item = $this->getMetadata()->get("clientDefs.{$entity->getEntityName()}.dynamicLogic.fields.$relation.$typeResult.conditionGroup", []);
                }
            }
        }

        if (!empty($item)) {
            $result = Condition::isCheck(Condition::prepare($entity, $item));
        }

        return $result;
    }

    /**
     * @param Entity $entity
     */
    private function setRelationFields(Entity $entity): void
    {
        foreach ($entity->getRelations() as $key => $relation) {
            if (isset($relation['key']) && $relation['type'] != 'manyMany') {
                $this->relationFields[$relation['key']] = $key;
            }
        }
    }

    /**
     * @param Entity $entity
     * @param $field
     * @return bool
     */
    private function isNullField(Entity $entity, $field): bool
    {
        $isNull = is_null($entity->get($field)) || $entity->get($field) === '';
        if ($isNull && !empty($relation = $entity->getFields()[$field]['relation'])) {
            $relationValue = $entity->get($relation);
            if ($relationValue instanceof \Espo\ORM\EntityCollection) {
                $isNull = $relationValue->count() === 0;
            } else {
                $isNull = is_null($relationValue);
            }
        }

        return $isNull;
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
        return $this->getInjection('eventManager')->dispatch($event->getArgument('target'), $action, $event);
    }

    protected function getPseudoTransactionManager(): PseudoTransactionManager
    {
        return $this->getInjection('pseudoTransactionManager');
    }

    protected function getDefaultRepositoryOptions(): array
    {
        return [
            'pseudoTransactionId'      => $this->getPseudoTransactionId(),
            'pseudoTransactionManager' => $this->getPseudoTransactionManager()
        ];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('queueManager');
    }
}
