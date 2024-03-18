<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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
 */

namespace Espo\Services;

use Atro\ORM\DB\RDB\Mapper;
use Caxy\HtmlDiff\HtmlDiff;
use Espo\Core\EventManager\Event;
use \ Atro\Core\Exceptions\Forbidden;
use \ Atro\Core\Exceptions\NotFound;

use Espo\ORM\Entity;

class Stream extends \Espo\Core\Services\Base
{
    protected $statusStyles = null;

    protected $statusFields = null;

    protected $successDefaultStyleList = ['Held', 'Closed Won', 'Closed', 'Completed', 'Complete', 'Sold'];

    protected $dangerDefaultStyleList = ['Not Held', 'Closed Lost', 'Dead'];

    protected function init()
    {
        parent::init();
        $this->addDependencyList([
            'entityManager',
            'config',
            'user',
            'metadata',
            'acl',
            'aclManager',
            'container'
        ]);
    }

    protected $emailsWithContentEntityList = ['Case'];

    protected $auditedFieldsCache = array();

    private $notificationService = null;

    protected function getServiceFactory()
    {
        return $this->getInjection('container')->get('serviceFactory');
    }

    protected function getAcl()
    {
        return $this->getInjection('acl');
    }

    protected function getAclManager()
    {
        return $this->getInjection('aclManager');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getFieldManager()
    {
        return $this->getInjection('container')->get('fieldManager');
    }

    protected function getNotificationService()
    {
        if (empty($this->notificationService)) {
            $this->notificationService = $this->getServiceFactory()->create('Notification');
        }
        return $this->notificationService;
    }

    protected function getStatusStyles()
    {
        if (empty($this->statusStyles)) {
            $this->statusStyles = $this->getMetadata()->get('entityDefs.Note.statusStyles', array());
        }
        return $this->statusStyles;
    }

    protected function getStatusFields()
    {
        if (is_null($this->statusFields)) {
            $this->statusFields = array();
            $scopes = $this->getMetadata()->get('scopes', array());
            foreach ($scopes as $scope => $data) {
                if (empty($data['statusField'])) continue;
                $this->statusFields[$scope] = $data['statusField'];
            }
        }
        return $this->statusFields;
    }

    public function afterRecordCreatedJob($data)
    {
        if (empty($data)) {
            return;
        }
        if (empty($data->entityId) || empty($data->entityType) || empty($data->userIdList)) {
            return;
        }
        $userIdList = $data->userIdList;
        $entityType = $data->entityType;
        $entityId = $data->entityId;

        $entity = $this->getEntityManager()->getEntity($entityType, $entityId);
        if (!$entity) {
            return;
        }

        foreach ($userIdList as $i => $userId) {
            $user = $this->getEntityManager()->getEntity('User', $userId);
            if (!$user) {
                continue;
            }
            if (!$this->getAclManager()->check($user, $entity, 'stream')) {
                unset($userIdList[$i]);
            }
        }
        $userIdList = array_values($userIdList);

        foreach ($userIdList as $i => $userId) {
            if ($this->checkIsFollowed($entity, $userId)) {
                unset($userIdList[$i]);
            }
        }
        $userIdList = array_values($userIdList);

        if (empty($userIdList)) {
            return;
        }

        $this->followEntityMass($entity, $userIdList);

        $noteList = $this->getEntityManager()->getRepository('Note')->where(array(
            'parentType' => $entityType,
            'parentId'   => $entityId
        ))->order('number', 'ASC')->find();

        foreach ($noteList as $note) {
            $this->getNotificationService()->notifyAboutNote($userIdList, $note);
        }
    }

    public function checkIsFollowed(Entity $entity, $userId = null)
    {
        if (empty($userId)) {
            $userId = $this->getUser()->id;
        }

        $connection = $this->getEntityManager()->getConnection();
        $res = $connection->createQueryBuilder()
            ->select('s.id')
            ->from($connection->quoteIdentifier('user_followed_record'), 's')
            ->where('s.entity_id = :entityId')
            ->setParameter('entityId', $entity->id)
            ->andWhere('s.entity_type = :entityType')
            ->setParameter('entityType', $entity->getEntityName())
            ->andWhere('s.user_id = :userId')
            ->setParameter('userId', $userId)
            ->fetchAllAssociative();

        return !empty($res);
    }

    public function followEntityMass(Entity $entity, array $sourceUserIdList): void
    {
        if (!$this->getMetadata()->get('scopes.' . $entity->getEntityName() . '.stream')) {
            return;
        }

        $userIdList = [];
        foreach ($sourceUserIdList as $id) {
            if ($id == 'system') {
                continue;
            }
            $userIdList[] = $id;
        }

        $userIdList = array_unique($userIdList);

        if (empty($userIdList)) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('user_followed_record'))
            ->where('entity_id = :entityId')
            ->setParameter('entityId', $entity->id)
            ->andWhere('user_id IN (:userIds)')
            ->setParameter('userIds', $userIdList, Mapper::getParameterType($userIdList))
            ->executeQuery();

        foreach ($userIdList as $userId) {
            $connection->createQueryBuilder()
                ->insert($connection->quoteIdentifier('user_followed_record'))
                ->setValue('entity_id', ':entityId')
                ->setParameter('entityId', $entity->id)
                ->setValue('entity_type', ':entityType')
                ->setParameter('entityType', $entity->getEntityType())
                ->setValue('user_id', ':userId')
                ->setParameter('userId', $userId)
                ->executeQuery();
        }
    }

    public function followEntity(Entity $entity, string $userId): bool
    {
        if ($userId == 'system') {
            return false;
        }
        if (!$this->getMetadata()->get('scopes.' . $entity->getEntityName() . '.stream')) {
            return false;
        }

        if (!$this->checkIsFollowed($entity, $userId)) {
            $connection = $this->getEntityManager()->getConnection();
            $connection->createQueryBuilder()
                ->insert($connection->quoteIdentifier('user_followed_record'))
                ->setValue('entity_id', ':entityId')
                ->setParameter('entityId', $entity->id)
                ->setValue('entity_type', ':entityType')
                ->setParameter('entityType', $entity->getEntityType())
                ->setValue('user_id', ':userId')
                ->setParameter('userId', $userId)
                ->executeQuery();
        }
        return true;
    }

    public function unfollowEntity(Entity $entity, $userId)
    {
        if (!$this->getMetadata()->get('scopes.' . $entity->getEntityName() . '.stream')) {
            return false;
        }

        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('user_followed_record'))
            ->where('entity_id = :entityId')
            ->setParameter('entityId', $entity->id)
            ->andWhere('entity_type = :entityType')
            ->setParameter('entityType', $entity->getEntityName())
            ->andWhere('user_id = :userId')
            ->setParameter('userId', $userId)
            ->executeQuery();

        return true;
    }

    public function unfollowAllUsersFromEntity(Entity $entity): void
    {
        if (empty($entity->id)) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('user_followed_record'))
            ->where('entity_id = :entityId')
            ->setParameter('entityId', $entity->id)
            ->andWhere('entity_type = :entityType')
            ->setParameter('entityType', $entity->getEntityName())
            ->executeQuery();
    }

    public function find($scope, $id, $params = [])
    {
        $entity = $this->getEntityManager()->getEntity($scope, $id);

        $onlyTeamEntityTypeList = $this->getOnlyTeamEntityTypeList($this->getUser());
        $onlyOwnEntityTypeList = $this->getOnlyOwnEntityTypeList($this->getUser());

        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checkEntity($entity, 'stream')) {
            throw new Forbidden();
        }

        $selectParams = [
            'offset'  => $params['offset'],
            'limit'   => $params['maxSize'],
            'orderBy' => $params['orderBy'],
            'order'   => 'DESC'
        ];

        if ($scope == 'User' && $id == null) {
            $where = [];
        } else {
            $where = [
                'OR' => [
                    [
                        'parentType' => $scope,
                        'parentId'   => $id
                    ],
                    [
                        'superParentType' => $scope,
                        'superParentId'   => $id
                    ]
                ]
            ];
        }

        if (count($onlyTeamEntityTypeList) || count($onlyOwnEntityTypeList)) {
            $selectParams['leftJoins'] = [['teams', 'teamsMiddle'], ['users', 'usersMiddle']];
            $selectParams['distinct'] = true;
            $where[] = [
                'OR' => [
                    'OR' => [
                        [
                            'relatedId!='   => null,
                            'relatedType!=' => array_merge($onlyTeamEntityTypeList, $onlyOwnEntityTypeList)
                        ],
                        [
                            'relatedId='      => null,
                            'superParentId'   => $id,
                            'superParentType' => $scope,
                            'parentId!='      => null,
                            'parentType!='    => array_merge($onlyTeamEntityTypeList, $onlyOwnEntityTypeList)
                        ],
                        [
                            'relatedId='  => null,
                            'parentType=' => $scope,
                            'parentId='   => $id
                        ]
                    ],
                    [
                        'OR' => [
                            [
                                'relatedId!='  => null,
                                'relatedType=' => $onlyTeamEntityTypeList
                            ],
                            [
                                'relatedId='  => null,
                                'parentType=' => $onlyTeamEntityTypeList
                            ]
                        ],
                        [
                            'OR' => [
                                'teamsMiddle.teamId' => $this->getUser()->getTeamIdList(),
                                'usersMiddle.userId' => $this->getUser()->id
                            ]
                        ]
                    ],
                    [
                        'OR'                 => [
                            [
                                'relatedId!='  => null,
                                'relatedType=' => $onlyOwnEntityTypeList
                            ],
                            [
                                'relatedId='  => null,
                                'parentType=' => $onlyOwnEntityTypeList
                            ]
                        ],
                        'usersMiddle.userId' => $this->getUser()->id
                    ]
                ]
            ];
        }

        if (!empty($params['after'])) {
            $where['createdAt>'] = $params['after'];
        }

        if (!empty($params['filter'])) {
            switch ($params['filter']) {
                case 'posts':
                    $where['type'] = 'Post';
                    break;
                case 'discussions':
                    $where['type'] = 'Discussion';
                    break;
                case 'discussionPosts':
                    $where['type'] = 'DiscussionPost';
                    break;
                case 'updates':
                    $where['type'] = ['Update', 'Status'];
                    break;
            }
        } else {
            $where['type!='] = 'DiscussionPost';
        }

        $ignoreScopeList = $this->getIgnoreScopeList($this->getUser());
        if (!empty($ignoreScopeList)) {
            $where[] = [
                'OR' => [
                    'relatedType'   => null,
                    'relatedType!=' => $ignoreScopeList
                ]
            ];
            $where[] = [
                'OR' => [
                    'parentType'   => null,
                    'parentType!=' => $ignoreScopeList
                ]
            ];
            if (in_array('Email', $ignoreScopeList)) {
                $where[] = [
                    'type!=' => ['EmailReceived', 'EmailSent']
                ];
            }
        }

        $selectParams['whereClause'] = $where;

        $collection = $this->getEntityManager()->getRepository('Note')->find($selectParams);

        foreach ($collection as $e) {
            if ($e->get('type') == 'Post' || $e->get('type') == 'DiscussionPost' || $e->get('type') == 'EmailReceived') {
                $e->loadAttachments();
            }

            if ($e->get('parentId') && $e->get('parentType')) {
                if (
                    ($e->get('parentId') != $id) ||
                    ($e->get('parentType') != $scope)
                ) {
                    $e->loadParentNameField('parent');
                }
            } else {
                if (!$e->get('isGlobal')) {
                    $targetType = $e->get('targetType');
                    if (!$targetType || $targetType === 'users' || $targetType === 'self') {
                        $e->loadLinkMultipleField('users');
                    }
                    if ($targetType !== 'users' && $targetType !== 'self') {
                        if (!$targetType || $targetType === 'teams') {
                            $e->loadLinkMultipleField('teams');
                        }
                    }
                }
            }

            if ($e->get('relatedId') && $e->get('relatedType')) {
                $e->loadParentNameField('related');
            }
            $this->prepareForOutput($e);
        }

        unset($where['createdAt>']);

        unset($selectParams['offset']);
        unset($selectParams['limit']);

        $selectParams['where'] = $where;
        $count = $this->getEntityManager()->getRepository('Note')->count($selectParams);

        return array(
            'total'      => $count,
            'collection' => $collection,
        );
    }

    public function prepareForOutput(Entity $entity)
    {
        if ($entity->get('type') == 'Post' || $entity->get('type') == 'EmailReceived') {
            $entity->loadAttachments();
        }

        if ($entity->get('type') == 'Update') {
            $data = $entity->get('data');

            $noteFieldDefs = [];

            if (!empty($data->fields)) {
                foreach ($data->fields as $field) {
                    $fieldDefs = $this->getMetadata()->get(['entityDefs', $entity->get('parentType'), 'fields', $field]);
                    $fieldDefs['label'] = $this->getInjection('container')->get('language')->translate($field, 'fields', $entity->get('parentType'));

                    $fieldDefs = $this->getInjection('container')->get('eventManager')
                        ->dispatch('StreamService', 'prepareNoteFieldDefs', new Event(['entity' => $entity, 'field' => $field, 'fieldDefs' => $fieldDefs]))
                        ->getArgument('fieldDefs');

                    if (empty($fieldDefs['type'])) {
                        continue;
                    }

                    $noteFieldDefs[$field] = $fieldDefs;

                    switch ($fieldDefs['type']) {
                        case 'text':
                            $became = nl2br($data->attributes->became->{$field});
                            $diff = (new HtmlDiff(html_entity_decode($data->attributes->was->{$field}), html_entity_decode($became)))->build();
                            $entity->set('diff', $diff);
                            break;
                        case 'wysiwyg':
                            $became = $data->attributes->became->{$field};
                            $diff = (new HtmlDiff(html_entity_decode($data->attributes->was->{$field}), html_entity_decode($became)))->build();
                            $entity->set('diff', $diff);
                            break;
                        case 'link':
                            foreach (['was', 'became'] as $k) {
                                if (!property_exists($data->attributes->{$k}, $field . 'Name') && property_exists($data->attributes->{$k}, $field . 'Id')) {
                                    $foreignEntity = $fieldDefs['entity'] ?? $this->getMetadata()->get(['entityDefs', $entity->get('parentType'), 'links', $field, 'entity']);
                                    if (!empty($foreignEntity)) {
                                        $foreign = $this->getEntityManager()->getRepository($foreignEntity)->get($data->attributes->{$k}->{$field . 'Id'});
                                        if (!empty($foreign)) {
                                            $data->attributes->{$k}->{$field . 'Name'} = $foreign->get('name');
                                        }
                                    }
                                }
                            }
                            break;
                        case 'enum':
                            if (!isset($fieldDefs['optionsIds']) || !isset($fieldDefs['options'])) {
                                break;
                            }
                            if (!empty($data->attributes->was->{$field})) {
                                $key = array_search($data->attributes->was->{$field}, $fieldDefs['optionsIds']);
                                if ($key !== false) {
                                    $data->attributes->was->{$field} = $fieldDefs['options'][$key];
                                }
                            }

                            if (!empty($data->attributes->became->{$field})) {
                                $key = array_search($data->attributes->became->{$field}, $fieldDefs['optionsIds']);
                                if ($key !== false) {
                                    $data->attributes->became->{$field} = $fieldDefs['options'][$key];
                                }
                            }
                            break;
                        case 'multiEnum':
                            if (!isset($fieldDefs['optionsIds']) || !isset($fieldDefs['options'])) {
                                break;
                            }

                            if (!empty($data->attributes->was->{$field})) {
                                $values = [];
                                foreach ($data->attributes->was->{$field} as $v) {
                                    $key = array_search($v, $fieldDefs['optionsIds']);
                                    if ($key !== false) {
                                        $values[] = $fieldDefs['options'][$key];
                                    } else {
                                        $values[] = $v;
                                    }
                                }

                                $data->attributes->was->{$field} = $values;
                            }

                            if (!empty($data->attributes->became->{$field})) {
                                $values = [];
                                foreach ($data->attributes->became->{$field} as $v) {
                                    $key = array_search($v, $fieldDefs['optionsIds']);
                                    if ($key !== false) {
                                        $values[] = $fieldDefs['options'][$key];
                                    } else {
                                        $values[] = $v;
                                    }
                                }

                                $data->attributes->became->{$field} = $values;
                            }
                            break;
                        case 'measure':
                            if (empty($fieldDefs['measureId'])) {
                                break;
                            }
                            if (!empty($data->attributes->was->{$field})) {
                                $unit = $this->getEntityManager()->getEntity('Unit', $data->attributes->was->{$field});
                                if (!empty($unit)) {
                                    $data->attributes->was->{$field . 'Name'} = $unit->get('name');
                                }
                            }

                            if (!empty($data->attributes->became->{$field})) {
                                $unit = $this->getEntityManager()->getEntity('Unit', $data->attributes->became->{$field});
                                if (!empty($unit)) {
                                    $data->attributes->became->{$field . 'Name'} = $unit->get('name');
                                }
                            }
                            break;
                        case 'extensibleEnum':
                            if (empty($fieldDefs['extensibleEnumId'])) {
                                break;
                            }
                            $repository = $this->getEntityManager()->getRepository('ExtensibleEnumOption');

                            if (!empty($data->attributes->was->{$field})) {
                                $option = $repository->getPreparedOption($fieldDefs['extensibleEnumId'], $data->attributes->was->{$field});
                                if (!empty($option)) {
                                    $data->attributes->was->{$field . 'Name'} = $option['name'];
                                    $data->attributes->was->{$field . 'OptionData'} = $option;
                                }
                            }

                            if (!empty($data->attributes->became->{$field})) {
                                $option = $repository->getPreparedOption($fieldDefs['extensibleEnumId'], $data->attributes->became->{$field});
                                if (!empty($option)) {
                                    $data->attributes->became->{$field . 'Name'} = $option['name'];
                                    $data->attributes->became->{$field . 'OptionData'} = $option;
                                }
                            }
                            break;
                        case 'extensibleMultiEnum':
                            if (empty($fieldDefs['extensibleEnumId'])) {
                                break;
                            }
                            $repository = $this->getEntityManager()->getRepository('ExtensibleEnumOption');

                            if (!empty($data->attributes->was->{$field})) {
                                $wasIds = $data->attributes->was->{$field};
                                if (is_string($wasIds)) {
                                    $wasIds = @json_decode($wasIds, true);
                                }
                                $options = $repository->getPreparedOptions($fieldDefs['extensibleEnumId'], $wasIds);
                                if (isset($options[0])) {
                                    $data->attributes->was->{$field . 'Names'} = array_column($options, 'name', 'id');
                                    $data->attributes->was->{$field . 'OptionsData'} = $options;
                                }
                            }

                            if (!empty($data->attributes->became->{$field})) {
                                $becameIds = $data->attributes->became->{$field};
                                if (is_string($becameIds)) {
                                    $becameIds = @json_decode($becameIds, true);
                                }
                                $options = $repository->getPreparedOptions($fieldDefs['extensibleEnumId'], $becameIds);
                                if (isset($options[0])) {
                                    $data->attributes->became->{$field . 'Names'} = array_column($options, 'name', 'id');
                                    $data->attributes->became->{$field . 'OptionsData'} = $options;
                                }
                            }
                            break;
                    }
                }
            }

            $entity->set('fieldDefs', $noteFieldDefs);
        }
    }

    protected function loadAssignedUserName(Entity $entity)
    {
        $user = $this->getEntityManager()->getEntity('User', $entity->get('assignedUserId'));
        if ($user) {
            $entity->set('assignedUserName', $user->get('name'));
        }
    }

    protected function processNoteTeamsUsers(Entity $note, Entity $entity)
    {
        $note->setAclIsProcessed();
        $note->set('teamsIds', []);
        $note->set('usersIds', []);

        if ($entity->hasLinkMultipleField('teams') && $entity->has('teamsIds')) {
            $teamIdList = $entity->get('teamsIds');
            if (!empty($teamIdList)) {
                $note->set('teamsIds', $teamIdList);
            }
        }

        $ownerUserIdAttribute = $this->getAclManager()->getImplementation($entity->getEntityType())->getOwnerUserIdAttribute($entity);
        if ($ownerUserIdAttribute && $entity->get($ownerUserIdAttribute)) {
            if ($entity->getAttributeParam($ownerUserIdAttribute, 'isLinkMultipleIdList')) {
                $userIdList = $entity->get($ownerUserIdAttribute);
            } else {
                $userId = $entity->get($ownerUserIdAttribute);
                $userIdList = [$userId];
            }
            $note->set('usersIds', $userIdList);
        }
    }

    public function noteCreate(Entity $entity)
    {
        $entityType = $entity->getEntityType();

        $note = $this->getEntityManager()->getEntity('Note');

        $note->set('type', 'Create');
        $note->set('parentId', $entity->id);
        $note->set('parentType', $entityType);

        if ($entity->has('accountId') && $entity->get('accountId')) {
            $note->set('superParentId', $entity->get('accountId'));
            $note->set('superParentType', 'Account');

            $this->processNoteTeamsUsers($note, $entity);
        }

        $data = [];

        if ($entity->get('assignedUserId')) {
            if (!$entity->has('assignedUserName')) {
                $this->loadAssignedUserName($entity);
            }
            $data['assignedUserId'] = $entity->get('assignedUserId');
            $data['assignedUserName'] = $entity->get('assignedUserName');
        }

        $statusFields = $this->getStatusFields();

        if (!empty($statusFields[$entityType])) {
            $field = $statusFields[$entityType];
            $value = $entity->get($field);
            if (!empty($value)) {
                $statusStyles = $this->getStatusStyles();
                $style = 'default';
                if (!empty($statusStyles[$entityType]) && !empty($statusStyles[$entityType][$value])) {
                    $style = $statusStyles[$entityType][$value];
                } else {
                    if (in_array($value, $this->successDefaultStyleList)) {
                        $style = 'success';
                    } else if (in_array($value, $this->dangerDefaultStyleList)) {
                        $style = 'danger';
                    }
                }
                $data['statusValue'] = $value;
                $data['statusField'] = $field;
                $data['statusStyle'] = $style;
            }
        }

        $note->set('data', $data);

        $this->getEntityManager()->saveEntity($note);
    }

    public function noteCreateRelated(Entity $entity, $parentType, $parentId)
    {
        $note = $this->getEntityManager()->getEntity('Note');

        $entityType = $entity->getEntityType();

        $note->set('type', 'CreateRelated');
        $note->set('parentId', $parentId);
        $note->set('parentType', $parentType);
        $note->set([
            'relatedType' => $entityType,
            'relatedId'   => $entity->id
        ]);

        $this->processNoteTeamsUsers($note, $entity);

        if ($entity->has('accountId') && $entity->get('accountId')) {
            $note->set('superParentId', $entity->get('accountId'));
            $note->set('superParentType', 'Account');
        }

        $this->getEntityManager()->saveEntity($note);
    }

    protected function getAuditedFieldsData(Entity $entity)
    {
        $entityType = $entity->getEntityType();

        if (!array_key_exists($entityType, $this->auditedFieldsCache)) {
            $fields = $this->getMetadata()->get('entityDefs.' . $entityType . '.fields');
            $auditedFields = array();
            foreach ($fields as $field => $d) {
                if (!empty($d['audited']) && !empty($d['type']) && !in_array($d['type'], ['rangeFloat', 'rangeInt'])) {
                    $auditedFields[$field] = array();
                    $auditedFields[$field]['actualList'] = $this->getFieldManager()->getActualAttributeList($entityType, $field);
                    $auditedFields[$field]['notActualList'] = $this->getFieldManager()->getNotActualAttributeList($entityType, $field);
                    $auditedFields[$field]['fieldType'] = $d['type'];
                }
            }
            $this->auditedFieldsCache[$entityType] = $auditedFields;
        }

        return $this->auditedFieldsCache[$entityType];
    }

    public function handleAudited($entity)
    {
        $auditedFields = $this->getAuditedFieldsData($entity);

        $updatedFieldList = [];
        $was = array();
        $became = array();

        foreach ($auditedFields as $field => $item) {
            $updated = false;
            foreach ($item['actualList'] as $attribute) {
                if ($entity->hasFetched($attribute) && $entity->isAttributeChanged($attribute)) {
                    $updated = true;
                }
            }
            if ($updated) {
                $updatedFieldList[] = $field;
                foreach ($item['actualList'] as $attribute) {
                    if ($entity->isAttributeChanged($attribute)) {
                        $was[$attribute] = $entity->getFetched($attribute);
                        $became[$attribute] = $entity->get($attribute);
                    }
                }
                foreach ($item['notActualList'] as $attribute) {
                    if ($entity->isAttributeChanged($attribute)) {
                        $was[$attribute] = $entity->getFetched($attribute);
                        $became[$attribute] = $entity->get($attribute);
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

    public function getEntityFolowerIdList(Entity $entity)
    {
        $connection = $this->getEntityManager()->getConnection();

        $condition = 's.user_id = u.id';
        $condition .= ' AND s.entity_id = :entityId';
        $condition .= ' AND s.entity_type = :entityType';

        $res = $connection->createQueryBuilder()
            ->select('u.id')
            ->from($connection->quoteIdentifier('user'), 'u')
            ->where('u.is_active = :isActive')
            ->setParameter('isActive', true, Mapper::getParameterType(true))
            ->innerJoin('u', $connection->quoteIdentifier('user_followed_record'), 's', $condition)
            ->setParameter('entityId', $entity->id)
            ->setParameter('entityType', $entity->getEntityType())
            ->fetchAllAssociative();

        return array_column($res, 'id');
    }

    public function getEntityFollowers(Entity $entity, $offset = 0, $limit = false)
    {
        $connection = $this->getEntityManager()->getConnection();

        $condition = 's.user_id = u.id';
        $condition .= ' AND s.entity_id = :entityId';
        $condition .= ' AND s.entity_type = :entityType';

        $res = $connection->createQueryBuilder()
            ->select('u.id, u.name')
            ->from($connection->quoteIdentifier('user'), 'u')
            ->innerJoin('u', $connection->quoteIdentifier('user_followed_record'), 's', $condition)
            ->setParameter('entityId', $entity->id)
            ->setParameter('entityType', $entity->getEntityType())
            ->where('u.is_active = :isActive')
            ->setParameter('isActive', true, Mapper::getParameterType(true))
            ->setFirstResult($offset)
            ->setMaxResults($limit ?? 200)
            ->orderBy('u.name', 'ASC')
            ->fetchAllAssociative();

        $data = array(
            'idList'  => [],
            'nameMap' => new \StdClass()
        );

        foreach ($res as $row) {
            $id = $row['id'];
            $data['idList'][] = $id;
            $data['nameMap']->$id = $row['name'];
        }

        return $data;
    }

    protected function getOnlyTeamEntityTypeList(\Espo\Entities\User $user)
    {
        $list = [];
        $scopes = $this->getMetadata()->get('scopes', []);
        foreach ($scopes as $scope => $item) {
            if ($scope === 'User') continue;
            if (empty($item['entity'])) continue;
            if (empty($item['object'])) continue;
            if (
                $this->getAclManager()->getLevel($user, $scope, 'read') === 'team'
            ) {
                $list[] = $scope;
            }
        }

        return $list;
    }

    protected function getOnlyOwnEntityTypeList(\Espo\Entities\User $user)
    {
        $list = [];
        $scopes = $this->getMetadata()->get('scopes', []);
        foreach ($scopes as $scope => $item) {
            if ($scope === 'User') continue;
            if (empty($item['entity'])) continue;
            if (empty($item['object'])) continue;
            if (
                $this->getAclManager()->getLevel($user, $scope, 'read') === 'own'
            ) {
                $list[] = $scope;
            }
        }
        return $list;
    }

    protected function getNotAllEntityTypeList(\Espo\Entities\User $user)
    {
        $list = [];
        $scopes = $this->getMetadata()->get('scopes', []);
        foreach ($scopes as $scope => $item) {
            if ($scope === 'User') continue;
            if (empty($item['entity'])) continue;
            if (empty($item['object'])) continue;
            if (
                $this->getAclManager()->getLevel($user, $scope, 'read') !== 'all'
            ) {
                $list[] = $scope;
            }
        }
        return $list;
    }

    protected function getIgnoreScopeList(\Espo\Entities\User $user)
    {
        $ignoreScopeList = [];
        $scopes = $this->getMetadata()->get('scopes', []);
        foreach ($scopes as $scope => $item) {
            if (empty($item['entity'])) continue;
            if (empty($item['object'])) continue;
            if (
                !$this->getAclManager()->checkScope($user, $scope, 'read')
                ||
                !$this->getAclManager()->checkScope($user, $scope, 'stream')
            ) {
                $ignoreScopeList[] = $scope;
            }
        }
        return $ignoreScopeList;
    }

    public function controlFollowersJob($data)
    {
        if (empty($data)) {
            return;
        }
        if (empty($data->entityId) || empty($data->entityType)) {
            return;
        }

        if (!$this->getEntityManager()->hasRepository($data->entityType)) return;

        $entity = $this->getEntityManager()->getEntity($data->entityType, $data->entityId);
        if (!$entity) return;

        $idList = $this->getEntityFolowerIdList($entity);

        $userList = $this->getEntityManager()->getRepository('User')->where(array(
            'id' => $idList
        ))->find();

        foreach ($userList as $user) {
            if (!$user->get('isActive')) {
                $this->unfollowEntity($entity, $user->id);
                continue;
            }

            if (!$this->getAclManager()->check($user, $entity, 'stream')) {
                $this->unfollowEntity($entity, $user->id);
                continue;
            }
        }
    }
}
