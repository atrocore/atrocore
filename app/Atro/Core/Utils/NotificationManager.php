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
use Atro\Core\KeyValueStorages\MemoryStorage;
use Atro\Core\QueueManager;
use Atro\NotificationTransport\AbstractNotificationTransport;
use Atro\NotificationTransport\NotificationOccurrence;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Repositories\NotificationRule;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Factories\AclManager as AclManagerFactory;
use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Atro\Entities\NotificationRule as RuleEntity;

class NotificationManager
{
    const NOTE_OCCURRENCES = [
        NotificationOccurrence::MENTION,
        NotificationOccurrence::NOTE_CREATED,
        NotificationOccurrence::NOTE_UPDATED,
        NotificationOccurrence::NOTE_DELETED
    ];

    protected Container $container;

    protected array $relationEntityData = [];

    protected array $notificationRules = [];

    protected array $notificationDisabled = [];

    protected array $subscribers = [];

    protected array $teamMembers = [];

    protected array $users = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function afterEntitySaved(Entity $entity): void
    {

        if (!$this->canSendNotification($entity)) {
            return;
        }

        $isNote = $entity->getEntityType() === 'Note';
        $noteHasParent = $entity->get('parentType') && $entity->get('parentId');

        if ($isNote && $entity->get('type') !== 'Post') {
            return;
        }

        $sync = $this->getConfig()->get('sendNotificationInSync', false);

        if ($entity->isNew()) {
            $this->sendNotificationsRelationEntity($entity, NotificationOccurrence::LINK, $sync);

            if ($isNote && !empty($entity->get('data')->mentions)) {
                $this->sendNoteNotifications(
                    NotificationOccurrence::MENTION,
                    $entity,
                    $sync
                );

            }

            if ($isNote && $noteHasParent) {
                $this->sendNoteNotifications(NotificationOccurrence::NOTE_CREATED, $entity, $sync);
            } else {
                $this->sendNotificationsByJob(
                    NotificationOccurrence::CREATION,
                    $entity->getEntityType(),
                    $entity->get('id'),
                    [],
                    $sync
                );
            }
        } else {
            if ($isNote && $noteHasParent) {
                $this->sendNoteNotifications(
                    NotificationOccurrence::NOTE_UPDATED,
                    $entity,
                    $sync
                );
            } else {
                $this->sendNotificationsByJob(
                    NotificationOccurrence::UPDATE,
                    $entity->getEntityType(),
                    $entity->get('id'),
                    [],
                    $sync
                );
            }
        }

        foreach (['ownerUser', 'assignedUser'] as $link) {
            if (($entity->isNew() && $entity->get($link . 'Id') !== null) || $entity->isAttributeChanged($link . 'Id')) {
                $this->sendNotificationsByJob(
                    $entity->get($link . 'Id') ? NotificationOccurrence::OWNERSHIP_ASSIGNMENT : NotificationOccurrence::UNLIKING_OWNERSHIP_ASSIGNMENT,
                    $entity->getEntityType(),
                    $entity->get('id'),
                    [
                        "isOwnership" => $link === 'ownerUser',
                        "isAssignment" => $link === 'assignedUser',
                        "entities" => [
                            [
                                "name" => $link,
                                "entityType" => "User",
                                "entityId" => $entity->get($link . 'Id')
                            ]
                        ]
                    ],
                    $sync
                );
            }
        }
    }

    public function afterEntityDeleted(Entity $entity): void
    {
        if (!$this->canSendNotification($entity)) {
            return;
        }

        $isNote = $entity->getEntityType() === 'Note';
        $noteHasParent = $entity->get('parentType') && $entity->get('parentId');

        $sync = $this->getConfig()->get('sendNotificationInSync', false);

        $this->sendNotificationsRelationEntity($entity, NotificationOccurrence::UNLINK, $sync);

        if ($isNote && $noteHasParent) {
            $this->sendNoteNotifications(
                NotificationOccurrence::NOTE_DELETED,
                $entity,
                $sync
            );
        } else {
            $this->sendNotificationsByJob(
                NotificationOccurrence::DELETION,
                $entity->getEntityType(),
                $entity->get('id'),
                [],
                $sync
            );
        }
    }

    public function sendNotificationsByJob(string $occurrence, string $entityType, string $entityId, array $additionalParams = [], bool $sync = false): void
    {
        $type = $entityType;
        if (in_array($occurrence, self::NOTE_OCCURRENCES) && !empty($additionalParams['entities'][0]['entityType'])) {
            $type = $additionalParams['entities'][0]['entityType'];
        }

        if (!$this->hasExistingRule($occurrence, $type)) {
            return;
        }

        $actionUser = $this->container->get('user');

        if(empty($actionUser)){
            return;
        }

        if ($sync) {
            $this->sendNotifications(
                $occurrence,
                $this->getEntityManager()->getRepository($entityType)->get($entityId),
                $actionUser,
                $additionalParams
            );
            return;
        }

        $jobData = [
            "occurrence" => $occurrence,
            "entityType" => $entityType,
            "entityId" => $entityId,
            "actionUserId" => $actionUser->get('id'),
            "params" => $additionalParams
        ];

        $this->getQueueManager()->push('Process Notification', 'QueueManagerNotificationSender', $jobData, 'Normal');
    }

    public function sendNotifications(string $occurrence, Entity $entity, User $actionUser, array $params = []): void
    {
        if (empty($this->getConfig()->get('sendOutNotifications'))) {
            $GLOBALS['log']->alert('Notification Not Sent: Send out Notification is deactivated.');
            return;
        }

        $parent = null;

        if (in_array($occurrence, self::NOTE_OCCURRENCES)
            && !empty($params['entities'][0]['entityType'])
            && !empty($params['entities'][0]['entityId'])
        ) {
            $parent = $this->getEntityManager()->getEntity($params['entities'][0]['entityType'], $params['entities'][0]['entityId']);
        }

        if ($parent && !$this->hasExistingRule($occurrence, $parent->getEntityType())) {
            return;
        }

        if (!$parent && !$this->hasExistingRule($occurrence, $entity->getEntityType())) {
            return;
        }

        $dataForTemplate = array_merge($this->transformData($params), [
            "occurrence" => $occurrence,
            "actionUser" => $actionUser,
            "siteUrl" => $this->getConfig()->get('siteUrl'),
            "entity" => $entity,
            "parent" => $parent
        ]);

        if ($occurrence === NotificationOccurrence::MENTION && $entity->getEntityType() === 'Note') {
            $this->sendMentionNotifications($entity, $actionUser, $dataForTemplate, $parent);
            return;
        }

        $offset = 0;
        $maxSize = 200;

        while (true) {
            $users = $this->getUsers($offset, $maxSize);

            $offset = $offset + $maxSize;

            if ($users->count() === 0) {
                break;
            }

            foreach ($users as $user) {
                if (!$this->userCanBeNotify($user, $occurrence, $entity, $actionUser, $parent)) {
                    continue;
                }

                $dataForTemplate['notifyUser'] = $user;

                $this->sendNotificationsToTransports(
                    $user,
                    $this->getUserNotificationRule(
                        $user->get('id'),
                        $occurrence,
                        $parent ? $parent->getEntityType() : $entity->getEntityType()
                    ),
                    $dataForTemplate
                );
            }
        }
    }

    public function sendNotificationsToTransports(
        User       $user,
        RuleEntity $notificationRule,
        array      $params
    ): void
    {
        // send notification for each transport
        foreach ($this->getMetadata()->get(['app', 'notificationTransports']) as $transportType => $transportClassName) {
            if ($notificationRule->isTransportActive($transportType) && !empty($template = $notificationRule->getTransportTemplate($transportType))) {
                $transport = $this->container->get($transportClassName);

                if (!($transport instanceof AbstractNotificationTransport)) {
                    continue;
                }

                try {
                    $transport->send($user, $template, $params);
                } catch (\Throwable $e) {
                    $occurrence = !empty($params['occurrence']) ? $params['occurrence'] : '';
                    $entity = !empty($params['entity']) ? $params['entity'] : '';
                    $GLOBALS['log']->error("Failed to send Notification[Occurrence: $occurrence][Entity: {$entity->getEntityType()}[User: {$user->id}:  . {$e->getMessage()}");
                }
            }
        }
    }

    protected function userCanBeNotify(User $user, string $occurrence, Entity $entity, User $actionUser, ?Entity $parent): bool
    {
        $preference = $this->getEntityManager()->getEntity('Preferences', $user->get('id'));

        if (empty($preference) || !$preference->get('receiveNotifications')) {
            $GLOBALS['log']->alert('Notification not sent: Receive notification is deactivate for user: ' . $user->get('id'));
            return false;
        }

        if ($entity->getEntityType() === 'Note') {
            if (!$this->checkByAclManager($user, $parent ?? $user, 'stream')) {
                return false;
            }
        } else if (!$this->checkByAclManager($user, $entity, 'read')) {
            return false;
        }

        $rule = $this->getUserNotificationRule(
            $user->get('id'),
            $occurrence,
            $parent ? $parent->getEntityType() : $entity->getEntityType()
        );

        if (empty($rule)) {
            return false;
        }

        if ($rule->get('ignoreSelfAction') && $user->get('id') === $actionUser->get('id')) {
            return false;
        }

        if ($occurrence === NotificationOccurrence::MENTION) {
            return true;
        }

        if ($rule->get('asOwner') && ($parent ?? $entity)->get('ownerUserId') === $user->get('id')) {
            return true;
        }

        if ($rule->get('asAssignee') && ($parent ?? $entity)->get('assignedUserId') === $user->get('id')) {
            return true;
        }

        if ($rule->get('asNotificationProfile')) {
            return true;
        }

        if ($rule->get('asFollower') && in_array($user->get('id'), $this->getSubscriberUserIds($parent ?? $entity))) {
            return true;
        }

        if ($rule->get('asTeamMember') && in_array($user->get('id'), $this->getTeamUserIds($parent ?? $entity))) {
            return true;
        }

        return false;
    }

    protected function canSendNotification(Entity $entity): bool
    {
        if ($this->getMemoryStorage()->get('importJobId')) {
            return false;
        }

        if ($this->notificationDisabled($entity->getEntityType())) {
            return false;
        }

        $isNote = $entity->getEntityType() === 'Note';

        if ($isNote && $entity->get('type') !== 'Post') {
            return false;
        }

        return true;
    }

    protected function sendMentionNotifications(Entity $entity, User $actionUser, array $param, ?Entity $parent): void
    {
        if ($entity->getEntityType() !== 'Note') {
            return;
        }

        foreach ($entity->get('data')->mentions as $mention) {
            if (empty($user = $this->getEntityManager()->getEntity('User', $mention->id))) {
                continue;
            }

            if (!$this->userCanBeNotify($user, NotificationOccurrence::MENTION, $entity, $actionUser, $parent)) {
                continue;
            }

            $param['notifyUser'] = $user;

            $this->sendNotificationsToTransports(
                $user,
                $this->getUserNotificationRule(
                    $user->get('id'),
                    NotificationOccurrence::MENTION,
                    $parent ? $parent->getEntityType() : $entity->getEntityType()
                ),
                $param
            );
        }
    }

    protected function getNotificationRule(string $notificationProfileId, string $occurrence, string $entityType): ?RuleEntity
    {
        $key = $entityType . '_' . $occurrence . '_' . $notificationProfileId;
        if (isset($this->notificationRules[$key])) {
            return $this->notificationRules[$key];
        }

        return $this->notificationRules[$key] = $this->getNotificationRuleRepository()->findOneFromCache($notificationProfileId, $occurrence, $entityType);
    }

    protected function getSubscriberUserIds(Entity $entity): array
    {
        $key = $entity->getEntityType() . '-' . $entity->get('id');

        if (!empty($this->subscribers[$key])) {
            return $this->subscribers[$key];
        }

        $connection = $this->getEntityManager()->getConnection();

        $userIds = $connection->createQueryBuilder()
            ->select('s.user_id')
            ->from($connection->quoteIdentifier('user_followed_record'), 's')
            ->where('s.entity_id = :entityId')
            ->setParameter('entityId', $entity->get('id'))
            ->andWhere('s.entity_type = :entityType')
            ->setParameter('entityType', $entity->getEntityType())
            ->fetchAllAssociative();

        return $this->subscribers[$key] = array_column($userIds, 'user_id');
    }

    protected function sendNotificationsRelationEntity(Entity $entity, string $occurrence, bool $sync = false)
    {
        if (!isset($this->relationEntityData[$entity->getEntityType()])) {
            $this->relationEntityData[$entity->getEntityType()] = [];
            if ($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'type']) === 'Relation') {
                $relationFields = $this->getEntityManager()->getRepository($entity->getEntityType())->getRelationFields();
                if (isset($relationFields[1]) && isset($relationFields[0])) {
                    $this->relationEntityData[$entity->getEntityType()]['field1'] = $relationFields[0] . 'Id';
                    $this->relationEntityData[$entity->getEntityType()]['entity1'] = $this->getMetadata()
                        ->get(['entityDefs', $entity->getEntityType(), 'links', $relationFields[0], 'entity']);

                    $this->relationEntityData[$entity->getEntityType()]['field2'] = $relationFields[1] . 'Id';
                    $this->relationEntityData[$entity->getEntityType()]['entity2'] = $this->getMetadata()
                        ->get(['entityDefs', $entity->getEntityType(), 'links', $relationFields[1], 'entity']);
                }
            }
        }

        if (empty($this->relationEntityData[$entity->getEntityType()])) {
            return;
        }

        $name = $occurrence === NotificationOccurrence::LINK ? "linkedEntity" : "unlinkedEntity";

        $this->sendNotificationsByJob(
            $occurrence,
            $this->relationEntityData[$entity->getEntityType()]['entity1'],
            $entity->get($this->relationEntityData[$entity->getEntityType()]['field1']),
            [
                "entities" => [
                    [
                        "name" => $name,
                        "entityId" => $entity->get($this->relationEntityData[$entity->getEntityType()]['field2']),
                        "entityType" => $this->relationEntityData[$entity->getEntityType()]['entity2'],
                    ],
                ],
            ],
            $sync
        );

        $this->sendNotificationsByJob(
            $occurrence,
            $this->relationEntityData[$entity->getEntityType()]['entity2'],
            $entity->get($this->relationEntityData[$entity->getEntityType()]['field2']),
            [
                "entities" => [
                    [
                        "name" => $name,
                        "entityId" => $entity->get($this->relationEntityData[$entity->getEntityType()]['field1']),
                        "entityType" => $this->relationEntityData[$entity->getEntityType()]['entity1'],
                    ],
                ],
            ],
            $sync
        );
    }

    protected function transformData(array $additionalParams): array
    {
        $data = [];
        foreach ($additionalParams as $key => $item) {
            if ($key === 'entities' && !empty($item)) {
                foreach ($item as $defs) {
                    if (empty($defs['name']) || empty($defs['entityId']) || empty($defs['entityType'])) {
                        continue;
                    }
                    $data[$defs['name']] = $this->getEntityManager()->getEntity($defs['entityType'], $defs['entityId']);
                }
                continue;
            }
            $data[$key] = $item;
        }
        return $data;
    }

    protected function getUserNotificationRule(string $userId, string $occurrence, string $entityType): ?RuleEntity
    {
        $preference = $this->getEntityManager()->getEntity('Preferences', $userId);
        $defaultNotificationProfileId = $this->getConfig()->get('defaultNotificationProfileId', '');

        if (empty($preference) || empty($preference->get('notificationProfileId')) || $preference->get('notificationProfileId') === 'default') {
            $notificationProfileId = $defaultNotificationProfileId;
        } else {
            $notificationProfileId = $preference->get('notificationProfileId');
        }


        $rule = $this->getNotificationRule($notificationProfileId, $occurrence, $entityType);

        if (empty($rule)) {
            return $this->getNotificationRule($notificationProfileId, $occurrence, '');
        }

        return $rule;
    }

    protected function hasExistingRule(string $occurrence, string $entityType): bool
    {
        $rules = $this->getMetadata()->get(['scopes', $entityType, 'notificationRuleIdByOccurrence', $occurrence], []);
        if (!empty($rules)) {
            return true;
        }

        $rules = $this->getMetadata()->get(['app', 'globalNotificationRuleIdByOccurrence', $occurrence]);

        return !empty($rules);
    }

    protected function notificationDisabled(string $entityType): bool
    {
        if (isset($this->notificationDisabled[$entityType])) {
            return $this->notificationDisabled[$entityType];
        }

        return $this->notificationDisabled[$entityType] = $this->getMetadata()->get(['scopes', $entityType, 'notificationDisabled'], false);
    }

    protected function sendNoteNotifications(string $occurrence, Entity $entity, bool $sync): void
    {
        $this->sendNotificationsByJob(
            $occurrence,
            $entity->getEntityType(),
            $entity->get('id'),
            [
                "entities" => [
                    [
                        "name" => "parent",
                        "entityType" => $entity->get('parentType'),
                        "entityId" => $entity->get('parentId')
                    ]
                ]
            ],
            $sync
        );
    }

    protected function getTeamUserIds(Entity $entity, ?array $teamsIds = null): array
    {
        $key = $entity->getEntityType() . '-' . $entity->get('id');

        if (!empty($this->teamMembers[$key])) {
            return $this->teamMembers[$key];
        }

        if ($teamsIds === null) {
            $entity->loadLinkMultipleField('teams');
            $teamsIds = $entity->get('teamsIds');
        }

        if (empty($teamsIds)) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();

        $userIds = $connection->createQueryBuilder()
            ->select('s.user_id')
            ->from($connection->quoteIdentifier('team_user'), 's')
            ->where('s.team_id IN (:teamIds)')
            ->andWhere('s.deleted = :false')
            ->setParameter('teamIds', $teamsIds, Mapper::getParameterType($teamsIds))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        return $this->teamMembers[$key] = array_column($userIds, 'user_id');
    }

    protected function getNotificationRuleRepository(): NotificationRule
    {
        return $this->getEntityManager()->getRepository('NotificationRule');
    }

    protected function checkByAclManager(User $user, Entity $parent, string $action): bool
    {
        return (AclManagerFactory::createAclManager($this->container))->check($user, $parent, $action);
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getQueueManager(): QueueManager
    {
        return $this->container->get('queueManager');
    }

    protected function getMemoryStorage(): MemoryStorage
    {
        return $this->container->get('memoryStorage');
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }

    protected function getUsers(int $offset, int $maxSize): EntityCollection
    {
        $key = 'User' . $offset . '_' . $maxSize;
        if (!empty($this->users[$key])) {
            return $this->users[$key];
        }

        return $this->users[$key] = $this->getEntityManager()->getRepository('User')
            ->where(['isActive' => true, 'id!=' => 'system'])
            ->limit($offset, $maxSize)
            ->find();
    }
}