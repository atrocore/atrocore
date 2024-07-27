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
use Atro\Core\QueueManager;
use Atro\NotificationTransport\AbstractNotificationTransport;
use Atro\NotificationTransport\NotificationOccurrence;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Repositories\NotificationRule;
use Doctrine\DBAL\ParameterType;
use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

class NotificationManager
{
    protected Container $container;
    protected array $relationEntityData;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function afterEntitySaved(Entity $entity):void
    {
        $isNote = $entity->getEntityType() === 'Note';

        if ($entity->isNew()) {
            $this->handleNotificationRelationEntity($entity, NotificationOccurrence::LINK);
            $this->handleNotificationByJob(
                $isNote ? NotificationOccurrence::NOTE_CREATED : NotificationOccurrence::CREATION,
                $entity->getEntityType(),
                $entity->get('id')
            );
        }

        $this->handleNotificationByJob(
            $isNote ? NotificationOccurrence::NOTE_UPDATED : NotificationOccurrence::UPDATE,
            $entity->getEntityType(),
            $entity->get('id')
        );

    }

    public function afterEntityDeleted(Entity $entity):void
    {
        $isNote = $entity->getEntityType() === 'Note';

        $this->handleNotificationRelationEntity($entity, NotificationOccurrence::UNLINK);

        $this->handleNotificationByJob(
            $isNote ? NotificationOccurrence::NOTE_DELETED : NotificationOccurrence::DELETION,
            $entity->getEntityType(),
            $entity->get('id')
        );
    }

    public function handleNotificationByJob(string $occurrence, string $entityType, string $entityId, array $additionalParams = []): void
    {
        if (!$this->hasExistingRule($occurrence, $entityType)) {
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

    public function handleNotification(string $occurrence, Entity $entity, User $actionUser): void
    {
        if (empty($this->getConfig()->get('sendOutNotifications'))) {
            $GLOBALS['log']->alert('Notification Not Sent: Send out Notification is deactivated.');
            return;
        }

        if (!$this->hasExistingRule($occurrence, $entity->getEntityType())) {
            return;
        }

        $id = $this->getNotificationRuleId($occurrence, $entity->getEntityType());
        $notificationRule = $this->getNotificationRuleRepository()->findFromCache($id);

        if (empty($notificationRule)) {
            return;
        }

        if (empty($notificationRule->get('isActive'))) {
            return;
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
            $preference = $this->getEntityManager()->getEntity('Preferences', $userId);
            return ($preference->get('notificationProfileId') ?? $this->getConfig()->get('defaultNotificationProfileId')) === $notificationRule->get('notificationProfileId');
        });

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

        foreach ($userList as $user) {
            foreach ($this->getMetadata()->get(['app', 'notificationTransports']) as $transportType => $transportClassName) {
                if ($notificationRule->isTransportActive($transportType) && !empty($template = $notificationRule->getTransportTemplate($transportType))) {
                    /** @var AbstractNotificationTransport $transport */
                    $transport = $this->container->get($transportClassName);
                    try {
                        $transport->send($user, $template, [
                            "config" => $this->getConfig(),
                            "occurrence" => $occurrence,
                            "entity" => $entity,
                            'entityType' => $entity->getEntityType(),
                            "actionUser" => $actionUser,
                            "notifyUser" => $user
                        ]);
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error("Failed to send Notification[Occurrence: $occurrence][Entity: {$entity->getEntityType()}[User: {$user->id}:  . {$e->getMessage()}");
                    }
                }
            }
        }
    }

    protected function getNotificationRuleId(string $occurrence, string $entityType): ?string
    {
        $notificationRuleId = $this->getMetadata()->get(['scopes', $entityType, 'notificationRuleIdByOccurrence', 'occurrence']);

        if (empty($notificationRuleId)) {
            $notificationRuleId = $this->getMetadata()->get(['app', 'globalNotificationRuleIdByOccurrence', $occurrence]);
        }

        return $notificationRuleId;
    }

    protected function hasExistingRule(string $occurrence, string $entityType): bool
    {
        return !empty($this->getNotificationRuleId($occurrence, $entityType));
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

    private function handleNotificationRelationEntity(Entity $entity, string $occurrence)
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

        $this->handleNotificationByJob(
            $occurrence,
            $this->relationEntityData[$entity->getEntityType()]['entity1'],
            $entity->get($this->relationEntityData[$entity->getEntityType()]['field1']),
            [
                "linkedEntityId" =>  $entity->get($this->relationEntityData[$entity->getEntityType()]['field2']),
                "linkedEntityType" =>  $entity->get($this->relationEntityData[$entity->getEntityType()]['field2']),
            ]
        );

        $this->handleNotificationByJob(
            $occurrence,
            $this->relationEntityData[$entity->getEntityType()]['entity2'],
            $entity->get($this->relationEntityData[$entity->getEntityType()]['field2']),
            [
                "linkedEntityId" =>  $entity->get($this->relationEntityData[$entity->getEntityType()]['field1']),
                "linkedEntityType" =>  $entity->get($this->relationEntityData[$entity->getEntityType()]['field1']),
            ]
        );

    }

    protected function getNotificationRuleRepository(): NotificationRule
    {
        return $this->getEntityManager()->getRepository('NotificationRule');
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
}