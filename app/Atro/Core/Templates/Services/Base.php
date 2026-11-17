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

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Http\RequestHeaders;
use Atro\Core\Services\AbstractRecordService;
use Atro\Core\Services\Interfaces\DeletableInterface;
use Atro\Core\Services\Interfaces\EditableInterface;
use Atro\Core\Services\Interfaces\LinkableInterface;
use Atro\Core\Services\Interfaces\MassActionsInterface;
use Atro\Core\Services\Interfaces\PersonalDataErasableInterface;
use Atro\Core\Services\Interfaces\SoftDeletableInterface;
use Atro\Core\Services\Interfaces\SortableInterface;
use Atro\Core\Services\Traits\DeletableTrait;
use Atro\Core\Services\Traits\EditableTrait;
use Atro\Core\Services\Traits\LinkableTrait;
use Atro\Core\Services\Traits\MassActionsTrait;
use Atro\Core\Services\Traits\PersonalDataErasableTrait;
use Atro\Core\Services\Traits\SoftDeletableTrait;
use Atro\Core\Services\Traits\SortableTrait;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;

/**
 * The service of the entities of type Base: all the operations on the records, the stream, the ownership,
 * merging, kanban, the derivatives and the attributes.
 */
class Base extends AbstractRecordService implements
    EditableInterface,
    LinkableInterface,
    SortableInterface,
    DeletableInterface,
    SoftDeletableInterface,
    MassActionsInterface,
    PersonalDataErasableInterface
{
    use EditableTrait;
    use LinkableTrait;
    use SortableTrait;
    use DeletableTrait;
    use SoftDeletableTrait;
    use MassActionsTrait;
    use PersonalDataErasableTrait;

    private $streamService;

    public function putAclMeta(Entity $entity): void
    {
        parent::putAclMeta($entity);

        $entity->setAvailableAction('stream', $this->getUser()->isAdmin() || $this->getAcl()->check($entity, 'stream'));

        // set permissions for additional actions of derivative entity
        if (!empty($masterEntity = $this->getMetadata()->get("scopes.{$entity->getEntityName()}.primaryEntityId")) && !empty($entity->get('masterRecordId'))) {
            $entity->setAvailableAction('updateMasterRecord', false);
            if ($this->getUser()->isAdmin()) {
                $entity->setAvailableAction('updateMasterRecord', true);
            } else {
                $masterRecord = $this->getEntityManager()->getRepository($masterEntity)->get($entity->get('masterRecordId'));
                if ($masterRecord) {
                    $entity->setAvailableAction('updateMasterRecord', $this->getAcl()->check($masterRecord, 'edit'));
                }
            }
        }
    }

    public function loadAdditionalFields(Entity $entity, bool $skipLinkMultiple = false)
    {
        parent::loadAdditionalFields($entity, $skipLinkMultiple);

        $this->loadFollowers($entity);
    }

    protected function validateEntityInput(Entity $entity, \stdClass $data): void
    {
        $this->validateArchivedRecordUpdate($entity, $data);
    }

    protected function prepareEntityToStore(Entity $entity, \stdClass $data): void
    {
        if ($entity->isNew()) {
            $this->populateDefaults($entity, $data);
        }

        $this->setOwnerAndAssignedUser($entity, $data);
        $this->checkForSkipComplete($entity, $data);

        // the completeness of the record replaces the check of its required fields
        if ($this->hasCompleteness($entity)) {
            $entity->skipValidation('requiredField');
        }
    }

    protected function validateEntityBeforeStore(Entity $entity, \stdClass $data): void
    {
        if (!$this->checkAssignment($entity)) {
            throw new Forbidden($entity->isNew() ? 'Assignment permission failure' : '');
        }
    }

    protected function getAllowedMassActions(): array
    {
        return array_merge(parent::getAllowedMassActions(), ['removeAttribute']);
    }

    protected function prepareDuplicateAttributes(string $id, \stdClass $attributes): \stdClass
    {

        if (!$this->getMetadata()->get(['scopes', $this->entityName, 'hasAttribute'])) {
            return $attributes;
        }

        $entity = $this->getEntity($id);
        if (!$entity) {
            return $attributes;
        }

        foreach ($entity->entityDefs['fields'] as $fieldName => $fieldDefs) {
            if (empty($fieldDefs['attributeId']) || empty($fieldDefs['duplicateIgnore'])) {
                continue;
            }
            unset($attributes->$fieldName);
            unset($attributes->{$fieldName . 'Id'});
            unset($attributes->{$fieldName . 'Name'});
            unset($attributes->{$fieldName . 'Ids'});
            unset($attributes->{$fieldName . 'Names'});
            unset($attributes->attributesDefs[$fieldName]);
        }

        return $attributes;
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_fromCollection)
            && !empty($this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'fields', 'cluster']))
        ) {
            $clusterItem = $this->getEntityManager()
                ->getRepository('ClusterItem')
                ->where(['entityName' => $entity->getEntityName(), 'entityId' => $entity->get('id')])
                ->findOne();

            if (!empty($clusterItem)) {
                $entity->set('clusterId', $clusterItem->get('clusterId'));
                $cluster = $clusterItem->get('cluster');
                if (!empty($cluster)) {
                    $entity->set('clusterName', $cluster->get('number'));
                }
            }
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $selectFields    = $selectParams['select'] ?? [];
        $clusterSelected = empty($selectFields) || in_array('clusterId', $selectFields);

        if (empty($this->getMemoryStorage()->get('exportJobId')) &&
            $clusterSelected &&
            !empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', 'cluster']))) {
            $entityIds = [];
            foreach ($collection as $entity) {
                $entityIds[] = $entity->get('id');
            }

            if (empty($entityIds)) {
                return;
            }

            $clusterItems = $this->getEntityManager()
                ->getRepository('ClusterItem')
                ->where(['entityName' => $this->entityType, 'entityId' => $entityIds])
                ->find();

            $itemByEntityId = [];
            $clusterIds     = [];
            foreach ($clusterItems as $item) {
                $itemByEntityId[$item->get('entityId')] = $item;
                $clusterIds[]                           = $item->get('clusterId');
            }

            $clusterById = [];
            if (!empty($clusterIds)) {
                $clusterCollection = $this->getEntityManager()
                    ->getRepository('Cluster')
                    ->select(['id', 'number'])
                    ->where(['id' => array_unique($clusterIds)])
                    ->find();
                foreach ($clusterCollection as $cluster) {
                    $clusterById[$cluster->get('id')] = $cluster;
                }
            }

            foreach ($collection as $entity) {
                $item = $itemByEntityId[$entity->get('id')] ?? null;
                if (!empty($item)) {
                    $entity->set('clusterId', $item->get('clusterId'));
                    $cluster = $clusterById[$item->get('clusterId')] ?? null;
                    if (!empty($cluster)) {
                        $entity->set('clusterName', $cluster->get('number'));
                    }
                }
            }
        }
    }

    protected function getStreamService(): \Espo\Services\Stream
    {
        if (empty($this->streamService)) {
            $this->streamService = $this->getServiceFactory()->create('Stream');
        }
        return $this->streamService;
    }

    protected function loadFollowers(Entity $entity): void
    {
        if ($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'streamDisabled'])) {
            return;
        }

        if ($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'followDisabled'])) {
            return;
        }

        $entity->loadLinkMultipleField('followers');
        $entity->set('isFollowed', in_array($this->getUser()->get('id'), is_array($entity->get('followersIds')) ? $entity->get('followersIds') : []));
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
        if (!empty($this->getMemoryStorage()->get('importJobId')) || $this->isPseudoTransaction()) {
            return true;
        }

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

        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if ($assignmentPermission === true || $assignmentPermission === 'yes' || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        $toProcess = false;

        if (!$entity->isNew()) {
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

        $assignmentPermission = $this->getAcl()->get('assignmentPermission');

        if ($assignmentPermission === true || $assignmentPermission === 'yes' || !in_array($assignmentPermission, ['team', 'no'])) {
            return true;
        }

        $toProcess = false;

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

    public function populateDefaults(Entity $entity, $data)
    {
        $user               = $this->getUser()->get('delegator');
        $forbiddenFieldList = null;

        if ($entity->hasAttribute('assignedUserId')) {
            $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit');
            if (in_array('assignedUser', $forbiddenFieldList)) {
                $entity->set('assignedUserId', $user->id);
                $entity->set('assignedUserName', $user->get('name'));
            }
        }

        if ($entity->hasLinkMultipleField('teams')) {
            if (is_null($forbiddenFieldList)) {
                $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit');
            }
            if (in_array('teams', $forbiddenFieldList)) {
                if ($user->get('defaultTeamId')) {
                    $defaultTeamId = $user->get('defaultTeamId');
                    $entity->addLinkMultipleId('teams', $defaultTeamId);
                    $teamsNames = $entity->get('teamsNames');
                    if (!$teamsNames || !is_object($teamsNames)) {
                        $teamsNames = (object)[];
                    }
                    $teamsNames->$defaultTeamId = $user->get('defaultTeamName');
                    $entity->set('teamsNames', $teamsNames);
                }
            }
        }
    }

    /**
     * @param Entity $entity
     * @param        $data
     */
    protected function checkForSkipComplete(Entity $entity, $data): void
    {
        if (!empty($data->skipComplete)) {
            $entity->skipComplete = true;
        }
    }

    public function getListKanban($params)
    {
        $params = $this
            ->dispatchEvent('beforeGetListKanban', new Event(['params' => $params, 'service' => $this]))
            ->getArgument('params');

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

        $statusFieldType = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $statusField, 'type']);
        if ($statusFieldType === 'enum') {
            if (empty($options = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $statusField, 'options']))) {
                throw new Error("No options for status field for entity type '{$this->entityType}'.");
            }
            $optionsIds = $options;
        } else {
            $extensibleEnumId = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $statusField, 'extensibleEnumId']);
            $extensibleEnum   = $this->getEntityManager()->getEntity('ExtensibleEnum', $extensibleEnumId);
            if (empty($extensibleEnum)) {
                throw new Error("Extensible enum not found: '{$extensibleEnumId}'.");
            }
            $options    = [];
            $optionsIds = [];
            foreach ($extensibleEnum->get('extensibleEnumOptions') ?? [] as $eeo) {
                $options[]    = $eeo->get('name');
                $optionsIds[] = $eeo->id;
            }
            if (empty($options)) {
                throw new Error("No options for status field for entity type '{$this->entityType}'.");
            }
        }

        $statusIgnoreList = $this->getMetadata()->get(['scopes', $this->entityType, 'kanbanStatusIgnoreList'], []);

        $additionalData = (object)[
            'groupList' => []
        ];

        foreach ($optionsIds as $k => $status) {
            if (in_array($status, $statusIgnoreList)) continue;
            if (!$status) continue;

            $selectParamsSub                  = $selectParams;
            $selectParamsSub['whereClause'][] = [
                $statusField => $status
            ];

            $o = (object)['name' => !array_key_exists($k, $options) ? $status : $options[$k]];

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

                    if (!empty(array_intersect($selectAttributeList, $this->getScriptFieldsList($e)))) {
                        $this->calculateScriptFields($e);
                    }
                } elseif (!empty($params['calculateScriptFields'])) {
                    $this->calculateScriptFields($e);
                }
                $this->prepareEntityForOutput($e);

                if (RequestHeaders::isMetaRequested()) {
                    $this->putMeta($e);
                    if (RequestHeaders::isMetaGroupRequested('availableActions')) {
                        $this->putAclMeta($e);
                    }
                }

                $collection[] = $e;
            }

            $o->total = $totalSub;
            $o->list  = $collectionSub->getValueMapList();

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
            ->dispatchEvent('afterGetListKanban', new Event(['params' => $params, 'service' => $this, 'result' => (object)['total' => $total, 'collection' => $collection, 'additionalData' => $additionalData]]))
            ->getArgument('result');
    }

    /**
     * An archived record cannot be changed. The only allowed change is unarchiving.
     */
    protected function validateArchivedRecordUpdate(Entity $entity, \stdClass $data): void
    {
        if (!$this->isArchivedRecord($entity)) {
            return;
        }

        foreach (get_object_vars($data) as $key => $value) {
            if ($key === 'isArchived' || $key === 'modifiedAt') {
                continue;
            }

            // service keys do not change a record, except those that carry changes
            if (str_starts_with($key, '_') && !in_array($key, ['__attributesToRemove', '_relationData', '_position'])) {
                continue;
            }

            throw new BadRequest($this->getInjection('language')->translate('archivedRecordCannotBeChanged', 'exceptions', 'Global'));
        }
    }

    public function follow($id, $userId = null)
    {
        $event = $this->dispatchEvent('beforeFollow', new Event(['id' => $id, 'service' => $this, 'userId' => $userId]));

        $id     = $event->getArgument('id');
        $userId = $event->getArgument('userId');

        $entity = $this->getRepository()->get($id);

        if (!$this->getAcl()->check($entity, 'stream')) {
            throw new Forbidden();
        }

        if (empty($userId)) {
            $userId = $this->getUser()->id;
        }

        return $this
            ->dispatchEvent('afterFollow', new Event(['entity' => $entity, 'service' => $this, 'userId' => $userId, 'result' => $this->getStreamService()->followEntity($entity, $userId)]))
            ->getArgument('result');
    }

    public function unfollow($id, $userId = null)
    {
        $event = $this->dispatchEvent('beforeUnfollow', new Event(['id' => $id, 'service' => $this, 'userId' => $userId]));

        $id     = $event->getArgument('id');
        $userId = $event->getArgument('userId');

        $entity = $this->getRepository()->get($id);

        if (!$this->getAcl()->check($entity, 'read')) {
            throw new Forbidden();
        }

        if (empty($userId)) {
            $userId = $this->getUser()->id;
        }

        return $this
            ->dispatchEvent('afterUnfollow', new Event(['entity' => $entity, 'service' => $this, 'userId' => $userId, 'result' => $this->getStreamService()->unfollowEntity($entity, $userId)]))
            ->getArgument('result');
    }

    public function massFollow(array $params, $userId = null)
    {
        $event = $this->dispatchEvent('beforeMassFollow', new Event(['params' => $params, 'service' => $this, 'userId' => $userId]));

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

            $collection = $this->getEntityManager()->getRepository($this->getEntityType())->find(array_merge($selectParams, ['select' => ['id']]));

            $ids = array_column($collection->toArray(), 'id');
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
            ->dispatchEvent('afterMassFollow', new Event(['params' => $params, 'service' => $this, 'userId' => $userId, 'result' => ['ids' => $resultIdList, 'count' => count($resultIdList)]]))
            ->getArgument('result');
    }

    public function massUnfollow(array $params, $userId = null)
    {
        $event = $this->dispatchEvent('beforeMassUnfollow', new Event(['params' => $params, 'service' => $this, 'userId' => $userId]));

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

            $collection = $this->getEntityManager()->getRepository($this->getEntityType())->find(array_merge($selectParams, ['select' => ['id']]));

            $ids = array_column($collection->toArray(), 'id');
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
            ->dispatchEvent('afterMassUnfollow', new Event(['params' => $params, 'service' => $this, 'userId' => $userId, 'result' => ['ids' => $resultIdList, 'count' => count($resultIdList)]]))
            ->getArgument('result');
    }

    protected function beforeMerge(Entity $entity, array $sourceList, $attributes)
    {
    }

    protected function afterMerge(Entity $entity, array $sourceList, $attributes)
    {
    }

    /**
     * @param IEntity $entity
     */
    protected function setOwnerAndAssignedUser(IEntity $entity, $attachment): void
    {
        $user = $this->getUser()->get('delegator');
        // has owner param
        $hasOwner = !empty($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'hasOwner']));

        if ((!property_exists($attachment, 'ownerUserId') || $entity->isNew()) && ($hasOwner || $entity->hasAttribute('ownerUserId')) && empty($entity->get('ownerUserId'))) {
            $entity->set('ownerUserId', $user->id);
        }

        // has assigned
        $hasAssigned = !empty($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'hasAssignedUser']));

        if ((!property_exists($attachment, 'assignedUserId') || $entity->isNew()) && ($hasAssigned || $entity->hasAttribute('assignedUserId')) && empty($entity->get('assignedUserId'))) {
            $entity->set('assignedUserId', $user->id);
        }
    }

    public function massRemoveAttribute(array $attributes, array $params)
    {
        $params = $this
            ->dispatchEvent('beforeMassRemoveAttribute', new Event(['params' => $params, 'service' => $this]))
            ->getArgument('params');

        $params['action']             = 'removeAttribute';
        $params['maxCountWithoutJob'] = $this->getConfig()->get('massUpdateMaxCountWithoutJob', 200);
        $params['maxChunkSize']       = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);
        $params['minChunkSize']       = $this->getConfig()->get('massUpdateMinChunkSize', 400);
        $params['additionalJobData']  = ["attributes" => $attributes];

        list($count, $errors, $sync, $job) = $this->executeMassAction($params);

        return $this
            ->dispatchEvent('afterMassRemoveAttribute',
                new Event(['service' => $this, 'result' => ['count' => $count, 'sync' => false, 'jobId' => $job->get('id')]]))
            ->getArgument('result');
    }

    public function merge($id, array $sourceIdList, \stdClass $attributes, bool $keepSources = false)
    {
        $repository = $this->getRepository();

        if (!isset($attributes->relationshipData) || !($attributes->relationshipData instanceof \stdClass)) {
            $attributes->relationshipData = new \stdClass();
        }
        if (!isset($attributes->input) || !($attributes->input instanceof \stdClass)) {
            $attributes->input = new \stdClass();
        }

        if (!empty($id)) {
            $entity = $this->getEntityManager()->getEntity($this->getEntityType(), $id);
        } else {
            $input = $attributes->input;
            unset($input->id);
            $id     = $this->createEntity($input);
            $entity = $this->getEntityManager()->getEntity($this->getEntityType(), $id);
        }

        if (!$entity) {
            throw new NotFound();
        }

        $relationshipData = json_decode(json_encode($attributes->relationshipData), true) ?? [];

        $sourceList = array();
        foreach ($sourceIdList as $sourceId) {
            if (is_object($sourceId)) {
                $source       = $sourceId;
                $sourceList[] = $source;
                continue;
            }
            $source = $this->getEntity($sourceId);
            if (!$source) {
                throw new BadRequest("Record with ID '{$sourceId}' not found.");
            }
            if (empty($keepSources) && !$this->getAcl()->check($source, 'delete')) {
                throw new Forbidden("You don't have permission to delete source record.");
            }

            $sourceList[] = $source;
        }

        $this->beforeMerge($entity, $sourceList, $attributes);

        if (empty($keepSources)) {
            $connection = $this->getEntityManager()->getConnection();

            $types = ['Post', 'EmailSent', 'EmailReceived'];

            foreach ($sourceList as $source) {
                $connection->createQueryBuilder()
                    ->update($connection->quoteIdentifier('note'), 'n')
                    ->set('parent_id', ':entityId')
                    ->set('parent_type', ':entityType')
                    ->where('n.type IN (:types)')
                    ->andWhere('n.parent_id = :sourceId')
                    ->andWhere('n.parent_type = :sourceType')
                    ->andWhere('n.deleted = :false')
                    ->setParameter('entityId', $entity->id)
                    ->setParameter('entityType', $entity->getEntityType())
                    ->setParameter('types', $types, Mapper::getParameterType($types))
                    ->setParameter('sourceId', $source->id)
                    ->setParameter('sourceType', $source->getEntityType())
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->executeQuery();
            }
        }

        foreach ($this->getMergeLinkList($relationshipData) as $link) {
            $method = 'applyMergeFor' . ucfirst($link);
            if (method_exists($this, $method)) {
                $this->$method($entity, $sourceList);
                continue;
            }

            foreach ($sourceList as $source) {
                $linkedList = [];
                if (!empty($source->get('__relationships'))) {
                    $linkedIds    = $source->get('__relationships')[$link]['ids'] ?? [];
                    $foreignScope = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links', $link, 'entity']);
                    if (!empty($linkedIds) && !empty($foreignScope)) {
                        $linkedList = $this->getEntityManager()->getRepository($foreignScope)->findByIds($linkedIds);
                    }
                } else {
                    if ($source->getEntityType() === $this->getEntityType()) {
                        $linkedList = $repository->findRelated($source, $link);
                    }
                }
                foreach ($linkedList as $linked) {
                    try {
                        $repository->relate($entity, $link, $linked);
                    } catch (UniqueConstraintViolationException|NotUnique $e) {
                    }
                }
            }
        }

        $upsertData = [];
        foreach ($relationshipData as $data) {
            if (empty($data['scope'])) {
                continue;
            }
            if (!empty($data['toUpsert'])) {
                foreach ($data['toUpsert'] as $payload) {
                    $input          = new \stdClass();
                    $input->entity  = $data['scope'];
                    $input->payload = (object)$payload;
                    $upsertData[]   = $input;
                }
            }

            if (!empty($data['toDelete'])) {
                $this->getRecordService($data['scope'])->massRemove([
                    'ids' => $data['toDelete']
                ]);
            }
        }

        $this->getServiceFactory()->create('Global')->upsert($upsertData);

        if (!empty($id)) {
            try {
                $attributes->input->_skipCheckForConflicts = true;
                $attributes->input->_sourceEntities        = $sourceList;
                $this->updateEntity($id, $attributes->input);
            } catch (NotModified $e) {

            }
        }

        if (empty($keepSources)) {
            foreach ($sourceList as $source) {
                $this->getEntityManager()->removeEntity($source);
            }
        }

        $this->afterMerge($entity, $sourceList, $attributes);

        return $entity;
    }

    public function getMergeLinkList(array $relationshipData): array
    {
        $mergeLinkList       = [];
        $linksDefs           = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links']);
        $customMergeLinkList = array_keys($relationshipData);
        $customEntityToMerge = array_column(array_values($relationshipData), 'scope');
        foreach ($linksDefs as $link => $d) {
            if (in_array($link, $this->getForbiddenLinksToMerge())) {
                continue;
            }

            if (in_array($link, $this->getMandatoryLinksToMerge())) {
                $mergeLinkList[] = $link;
                continue;
            }

            if (!empty($d['notMergeable'])) {
                continue;
            }

            if (in_array($link, $customMergeLinkList)) {
                continue;
            }

            if (!empty($d['type']) && in_array($d['type'], ['hasMany'])) {
                if (empty($d['entity']) || empty($d['foreign'])) {
                    continue;
                }

                if (in_array($d['entity'], $customEntityToMerge)) {
                    continue;
                }

                $mergeLinkList[] = $link;
            }
        }

        return $mergeLinkList;
    }

    protected function getMandatoryLinksToMerge(): array
    {
        return [];
    }

    protected function getForbiddenLinksToMerge(): array
    {
        return [];
    }

    /**
     * The fields of the primary record that are not copied to the derivative record.
     */
    protected function getFieldsNotCopiedFromPrimary(): array
    {
        return [];
    }

    public function createFromPrimaryRecord(string $id, \stdClass $input): Entity
    {
        $primaryEntityId = $this->getMetadata()->get(['scopes', $this->getentityType(), 'primaryEntityId']);
        if (empty($primaryEntityId)) {
            throw new BadRequest("{$this->getEntityType()} is not of type 'Derivative'");
        }

        $primaryEntity = $this->getEntityManager()->getEntity($primaryEntityId, $id);
        if (empty($primaryEntity)) {
            throw new NotFound("{$this->getEntityType()} with id $id not found");
        }

        $this->getEntityManager()->getAttributeFieldConverter()->putAttributesToEntity($primaryEntity);
        if ($primaryEntity->hasField('attributesDefs')) {
            $input->attributesDefs = json_decode(json_encode($primaryEntity->get('attributesDefs') ?? []));
        }

        $input->masterRecordId = $primaryEntity->get('id');
        $skippedFields         = [
            'id', 'createdAt', 'modifiedAt', 'createdById', 'modifiedById',
            'ownerUserId', 'assignedUserId', 'teamsIds', 'followersIds', 'deleted'];

        foreach ($primaryEntity->toArray() as $field => $value) {
            if (in_array($field, $skippedFields)) {
                continue;
            }

            if (!empty($primaryEntity->entityDefs[$field]['notStorable']) || !empty($this->getMetadata()->get(['entityDefs', $this->entityName, 'fields', $field, 'notStorable']))) {
                continue;
            }

            if (in_array($field, $this->getFieldsNotCopiedFromPrimary())) {
                continue;
            }

            if (isset($input->$field)) {
                continue;
            }

            $input->$field = $value;
        }

        // build input for many-to-many relations
        foreach ($primaryEntity->getRelations() as $link => $defs) {
            if (!empty($defs['relationName'])) {
                if (in_array($link, ['children', 'parents', 'followers', 'teams']) || empty($defs['key']) || empty($defs['midKeys'][0])) {
                    continue;
                }

                $ids = $primaryEntity->getLinkMultipleIdList($link);
                if (empty($ids)) {
                    continue;
                }

                $relationEntities = $this->getEntityManager()->getRepository(ucfirst($defs['relationName']))
                    ->where([
                        $defs['midKeys'][0] => $primaryEntity->get($defs['key']),
                        $defs['midKeys'][1] => $ids
                    ])
                    ->find();

                $idsField     = $link . 'Ids';
                $columnsField = $link . 'Columns';

                foreach ($relationEntities as $item) {
                    $input->$idsField[] = $item->get($defs['midKeys'][1]);

                    if (!isset($input->$columnsField)) {
                        $input->$columnsField = new \stdClass();
                    }

                    foreach ($item->toArray() as $field => $value) {
                        if (!empty($this->getMetadata()->get(['entityDefs', $item->getEntityName(), 'fields', $field, 'notStorable']))) {
                            continue;
                        }
                        if (in_array($field, array_merge($skippedFields, $defs['midKeys']))) {
                            continue;
                        }

                        if (!isset($input->$columnsField->{$item->get($defs['midKeys'][1])})) {
                            $input->$columnsField->{$item->get($defs['midKeys'][1])} = new \stdClass();
                        }

                        $input->$columnsField->{$item->get($defs['midKeys'][1])}->$field = $value;
                    }
                }
            }
        }

        $id = $this->createEntity($input);

        return $this->getEntityManager()->getEntity($this->getEntityType(), $id);
    }
}
