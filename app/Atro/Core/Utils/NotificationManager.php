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

    protected array $notificationRuleIds = [];

    protected array $userToNotifyIds = [];

    protected array $notificationDisabled = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function afterEntitySaved(Entity $entity): void
    {

        if(!$this->canSendNotification($entity)){
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
            if ($entity->isAttributeChanged($link . 'Id')) {
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
        if(!$this->canSendNotification($entity)){
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

        if ($sync) {
            $this->sendNotifications(
                $occurrence,
                $this->getEntityManager()->getRepository($entityType)->get($entityId),
                $this->container->get('user'),
                $additionalParams
            );
            return;
        }

        $jobData = [
            "occurrence" => $occurrence,
            "entityType" => $entityType,
            "entityId" => $entityId,
            "actionUserId" => !empty($user = $this->container->get('user')) ? $user->get('id') : null,
            "params" => $additionalParams
        ];

        $this->getQueueManager()->push('Process Notification', 'QueueManagerNotificationSender', $jobData, 'Crucial');
    }

    public function sendNotifications(string $occurrence, Entity $entity, User $actionUser, array $params = []): void
    {
        if (empty($this->getConfig()->get('sendOutNotifications'))) {
            $GLOBALS['log']->alert('Notification Not Sent: Send out Notification is deactivated.');
            return;
        }

        if ($occurrence === NotificationOccurrence::MENTION && $entity->getEntityType() === 'Note') {
            $this->sendMentionNotifications($occurrence, $entity, $actionUser, $params);
            return;
        }

        $parent = null;
        if (in_array($occurrence, self::NOTE_OCCURRENCES)
            && !empty($params['entities'][0]['entityType'])
            && !empty($params['entities'][0]['entityId'])
        ) {
            $parent = $this->getEntityManager()->getEntity($params['entities'][0]['entityType'], $params['entities'][0]['entityId']);
        }

        $usersToNotifyIds = $this->getUserToNotifyIds($occurrence, $parent ?? $entity, $actionUser);;

        if (empty($usersToNotifyIds)) {
            return;
        }

        $userList = $this
            ->getEntityManager()
            ->getRepository('User')
            ->where(
                [
                    'isActive' => true,
                    'id' => $usersToNotifyIds
                ]
            )
            ->find();

        $finalUserList = [];
        foreach ($userList as $user) {
            if (in_array($occurrence, self::NOTE_OCCURRENCES)) {
                if ($parent && !$this->checkByAclManager($user, $parent, 'stream')) {
                    continue;
                }
            } else if (!$this->checkByAclManager($user, $entity, 'read')) {
                continue;
            }

            $finalUserList[] = $user;
        }

        $this->sendNotificationsToTransports($finalUserList, $occurrence, $entity, $actionUser, $params, $parent);
    }

    protected function canSendNotification(Entity  $entity): bool
    {
        if ($this->getMemoryStorage()->get('importJobId')) {
            return false;
        }

        if ($this->notificationDisabled($entity->getEntityType())) {
            return false;
        }

        if ($entity->getEntityType() === 'QueueItem' && $entity->get('serviceName') === 'QueueManagerNotificationSender') {
            return false;
        }

        $isNote = $entity->getEntityType() === 'Note';

        if ($isNote && $entity->get('type') !== 'Post') {
            return false;
        }

        return true;
    }

    protected function sendMentionNotifications(string $occurrence, Entity $entity, User $actionUser, array $additionalParams)
    {
        $userList = [];
        $parent = null;
        if ($entity->get('parentId') && $entity->get('parentType')) {
            $parent = $this->getEntityManager()->getEntity($entity->get('parentType'), $entity->get('parentId'));
        }

        if ($parent && !$this->hasExistingRule($occurrence, $parent->getEntityType())) {
            return;
        }

        if (!$parent && !$this->hasExistingRule($occurrence, $entity->getEntityType())) {
            return;
        }

        $notificationRule = $this->getNotificationRule($occurrence, $parent ? $parent->getEntityType() : $entity->getEntityType());

        if (empty($notificationRule)) {
            return;
        }

        foreach ($entity->get('data')->mentions as $mention) {
            if ($notificationRule->get('ignoreSelfAction') && $mention->id === $actionUser->id) {
                continue;
            }

            if (empty($user = $this->getEntityManager()->getEntity('User', $mention->id))) {
                continue;
            }

            if ($this->getUserNotificationProfileId($user->get('id')) !== $notificationRule->get('notificationProfileId')) {
                continue;
            }

            if ($parent && !$this->checkByAclManager($user, $parent, 'stream')) {
                continue;
            }

            if (!$parent && !$this->checkByAclManager($user, $entity, 'read')) {
                continue;
            }

            $userList[] = $user;
        }

        if (!empty($userList)) {
            $this->sendNotificationsToTransports($userList, $occurrence, $entity, $actionUser, $additionalParams, $parent);
        }
    }

    protected function sendNotificationsToTransports(
        array   $userList,
        string  $occurrence,
        Entity  $entity,
        User    $actionUser,
        array   $additionalParams,
        ?Entity $parent = null
    ): void
    {
        $notificationRule = $this->getNotificationRule($occurrence, $parent ? $parent->getEntityType() : $entity->getEntityType());

        if (empty($notificationRule)) {
            return;
        }

        foreach ($userList as $user) {
            $data = [
                "occurrence" => $occurrence,
                "entity" => $entity,
                'entityType' => $entity->getEntityType(),
                "actionUser" => $actionUser,
                "notifyUser" => $user
            ];

            // transformData process the additional params en load entity if necessary for the template
            $dataForTemplate = array_merge($data, $this->transformData($additionalParams));

            // send notification for each transport
            foreach ($this->getMetadata()->get(['app', 'notificationTransports']) as $transportType => $transportClassName) {
                if ($notificationRule->isTransportActive($transportType) && !empty($template = $notificationRule->getTransportTemplate($transportType))) {
                    /** @var AbstractNotificationTransport $transport */
                    $transport = $this->container->get($transportClassName);
                    try {
                        $transport->send($user, $template, $dataForTemplate);
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error("Failed to send Notification[Occurrence: $occurrence][Entity: {$entity->getEntityType()}[User: {$user->id}:  . {$e->getMessage()}");
                    }
                }
            }
        }
    }

    protected function getNotificationRule(string $occurrence, string $entityType): ?RuleEntity
    {
        if (isset($this->notificationRuleIds[$entityType][$occurrence])) {
            return $this->notificationRuleIds[$entityType][$occurrence];
        }
        $notificationRuleId = $this->getMetadata()->get(['scopes', $entityType, 'notificationRuleIdByOccurrence', $occurrence]);

        if (empty($notificationRuleId)) {
            $notificationRuleId = $this->getMetadata()->get(['app', 'globalNotificationRuleIdByOccurrence', $occurrence]);
        }

        if (!empty($notificationRuleId)) {
            return $this->notificationRuleIds[$entityType][$occurrence] = $this->getNotificationRuleRepository()->getFromCache($notificationRuleId);
        }

        return null;
    }

    protected function getSubscriberUserIds(Entity $entity): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $userIds = $connection->createQueryBuilder()
            ->select('s.user_id')
            ->from($connection->quoteIdentifier('user_followed_record'), 's')
            ->where('s.entity_id = :entityId')
            ->setParameter('entityId', $entity->get('id'))
            ->andWhere('s.entity_type = :entityType')
            ->setParameter('entityType', $entity->getEntityType())
            ->fetchAllAssociative();

        return array_column($userIds, 'user_id');
    }

    protected function getTeamUserIds(Entity $entity): array
    {
        $entity->loadLinkMultipleField('teams');
        $teamsIds = $entity->get('teamsIds');

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

        return array_column($userIds, 'user_id');
    }

    protected function getNotificationProfileUserIds($notificationProfileId)
    {
        $connection = $this->getEntityManager()->getConnection();

        $userIds = $connection->createQueryBuilder()
            ->select('s.id')
            ->from($connection->quoteIdentifier('preferences'), 's')
            ->where("s.data like :notificationProfileId")
            ->setParameter('notificationProfileId', '%"' . $notificationProfileId . '"%')
            ->fetchAllAssociative();

        return array_column($userIds, 'id');
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
                        "entityType" => $entityType = $this->relationEntityData[$entity->getEntityType()]['entity2'],
                    ],
                ],
                $name . "Type" => $entityType
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
                        "entityType" => $entityType = $this->relationEntityData[$entity->getEntityType()]['entity1'],
                    ],
                ],
                $name . "Type" => $entityType
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

    protected function getUserNotificationProfileId(string $userId): string
    {
        $preference = $this->getEntityManager()->getEntity('Preferences', $userId);
        if (empty($preference)) {
            return $this->getConfig()->get('defaultNotificationProfileId');
        }

        return $preference->get('notificationProfileId') ?? $this->getConfig()->get('defaultNotificationProfileId');
    }

    protected function getUserToNotifyIds(string $occurrence, Entity $entity, User $actionUser): array
    {
        if (isset($this->userToNotifyIds[$occurrence][$entity->getEntityType()][$actionUser->get('id')])) {
            return $this->userToNotifyIds[$occurrence][$entity->getEntityType()][$actionUser->get('id')];
        }

        $notificationRule = $this->getNotificationRule($occurrence, $entity->getEntityType());

        if (empty($notificationRule)) {
            return [];
        }

        $usersToNotifyIds = [];

        if ($notificationRule->get('asOwner') && !empty($entity->get('ownerUserId'))) {
            $usersToNotifyIds[] = $entity->get('ownerUserId');
        }

        if ($notificationRule->get('asAssignee') && !empty($entity->get('assignedUserId'))) {
            $usersToNotifyIds[] = $entity->get('assignedUserId');
        }

        if ($notificationRule->get('asFollower')) {
            $usersToNotifyIds = array_merge($usersToNotifyIds, $this->getSubscriberUserIds($entity));
        }

        if ($notificationRule->get('asTeamMember')) {
            $usersToNotifyIds = array_merge($usersToNotifyIds, $this->getTeamUserIds($entity));
        }

        if ($notificationRule->get('asNotificationProfile')) {
            $usersToNotifyIds = array_merge($usersToNotifyIds, $this->getNotificationProfileUserIds($notificationRule->get('notificationProfileId')));
        }

        $usersToNotifyIds = array_unique($usersToNotifyIds);

        if ($notificationRule->get('ignoreSelfAction')) {
            $key = array_search($actionUser->id, $usersToNotifyIds);
            if ($key !== false) {
                unset($usersToNotifyIds[$key]);
            }
        }
        // we select only the user who as configure the notificationProfile link to this rule
        $usersToNotifyIds = array_filter($usersToNotifyIds, function ($userId) use ($notificationRule) {
            return $this->getUserNotificationProfileId($userId) === $notificationRule->get('notificationProfileId');
        });

        return $this->userToNotifyIds[$occurrence][$entity->getEntityType()][$actionUser->get('id')] = array_values($usersToNotifyIds);
    }

    protected function hasExistingRule(string $occurrence, string $entityType): bool
    {
        return !empty($this->getNotificationRule($occurrence, $entityType));
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
                "parentType" => $entity->get('parentType'),
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

    private function getNotificationRuleRepository(): NotificationRule
    {
        return $this->getEntityManager()->getRepository('NotificationRule');
    }

    private function checkByAclManager(User $user, Entity $parent, string $action): bool
    {
        return (AclManagerFactory::createAclManager($this->container))->check($user, $parent, $action);
    }

    private function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    private function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    private function getConfig(): Config
    {
        return $this->container->get('config');
    }

    private function getQueueManager(): QueueManager
    {
        return $this->container->get('queueManager');
    }

    private function getMemoryStorage(): MemoryStorage
    {
        return $this->container->get('memoryStorage');
    }

    private function getLanguage(): Language
    {
        return $this->container->get('language');
    }
}