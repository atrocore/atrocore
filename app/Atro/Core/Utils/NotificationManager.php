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
use Atro\NotificationTransport\AbstractNotificationTransport;
use Atro\NotificationTransport\NotificationOccurrence;
use Atro\Repositories\NotificationRule;
use Espo\Core\Factories\AclManager as AclManagerFactory;
use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Atro\Entities\NotificationRule as RuleEntity;
use Atro\Core\Utils\Note as NoteUtil;

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
        $hasSentUpdateOccurrence = false;

        if ($isNote && $entity->get('type') !== 'Post') {
            return;
        }

        if ($entity->isNew()) {
            $this->sendNotificationsRelationEntity($entity, NotificationOccurrence::LINK);

            if ($isNote && !empty($entity->get('data')->mentions)) {
                $this->sendNoteNotifications(
                    NotificationOccurrence::MENTION,
                    $entity
                );

            }

            if ($isNote && $noteHasParent) {
                $this->sendNoteNotifications(NotificationOccurrence::NOTE_CREATED, $entity);
            } else {
                $this->sendNotifications(NotificationOccurrence::CREATION, $entity);
            }
        } else {
            if ($isNote && $noteHasParent) {
                $this->sendNoteNotifications(NotificationOccurrence::NOTE_UPDATED, $entity);
            } else {
                $this->sendNotifications(NotificationOccurrence::UPDATE, $entity);
                $hasSentUpdateOccurrence  = true;
            }
        }

        foreach (['ownerUser', 'assignedUser'] as $link) {
            if (($entity->isNew() && $entity->get($link . 'Id') !== null) || (!$hasSentUpdateOccurrence && $entity->isAttributeChanged($link . 'Id'))) {
                $this->sendNotifications(
                    $entity->get($link . 'Id') ? NotificationOccurrence::OWNERSHIP_ASSIGNMENT : NotificationOccurrence::UNLIKING_OWNERSHIP_ASSIGNMENT,
                    $entity,
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
                    ]
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

        $this->sendNotificationsRelationEntity($entity, NotificationOccurrence::UNLINK);

        if ($isNote && $noteHasParent) {
            $this->sendNoteNotifications(
                NotificationOccurrence::NOTE_DELETED,
                $entity
            );
        } else {
            $this->sendNotifications(
                NotificationOccurrence::DELETION,
                $entity
            );
        }
    }

    public function sendNotifications(string $occurrence, Entity $entity, array $params = []): void
    {
        $actionUser = $this->container->get('user');

        if (empty($actionUser)) {
            return;
        }

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

        $dataForTemplate = [];

        foreach ($this->getMetadata()->get(['app', 'activeNotificationProfilesIds'], []) as $notificationProfileId) {

            $rule = $this->getNotificationRule($notificationProfileId, $occurrence, $parent ? $parent->getEntityType() : $entity->getEntityType());

            if (empty($rule) || empty($rule->receiverUsers)) {
                continue;
            }

            foreach ($rule->receiverUsers as $user) {

                if (!$this->userCanBeNotify($user, $occurrence, $entity, $actionUser, $rule, $parent)) {
                    continue;
                }

                if(empty($dataForTemplate)){
                    if($occurrence === NotificationOccurrence::UPDATE) {
                        $updateData = $this->getUpdateData($entity);
                        if(empty($updateData)){
                            break;
                        }
                        $params['updateData'] = $updateData;
                    }
                    $dataForTemplate = array_merge($this->transformData($params), [
                        "occurrence" => $occurrence,
                        "actionUser" => $actionUser,
                        "siteUrl" => $this->getConfig()->get('siteUrl'),
                        "entity" => $entity,
                        "parent" => $parent
                    ]);
                }

                $dataForTemplate['notifyUser'] = $user;

                $this->sendNotificationsToTransports(
                    $user,
                    $rule,
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

    protected function userCanBeNotify(
        User       $user,
        string     $occurrence,
        Entity     $entity,
        User       $actionUser,
        RuleEntity $rule,
        ?Entity    $parent
    ): bool
    {

        if ($entity->getEntityType() === 'Note') {
            if (!$this->checkByAclManager($user, $parent ?? $user, 'stream')) {
                return false;
            }
        } else if (!$this->checkByAclManager($user, $entity, 'read')) {
            return false;
        }

        if ($rule->get('ignoreSelfAction') && $user->get('id') === $actionUser->get('id')) {
            return false;
        }

        if ($occurrence === NotificationOccurrence::MENTION && $entity->getEntityType() === 'Note') {
            foreach ($entity->get('data')->mentions as $mention) {
                if ($user->id === $mention->id) {
                    return true;
                }
            }
            return false;
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

        if ($rule->get('asTeamMember') && !empty(array_intersect($this->getTeamIds($parent ?? $entity), $user->get('teamsIds')))) {
            return true;
        }

        if ($rule->get('asFollower') && in_array($user->get('id'), $this->getSubscriberUserIds($parent ?? $entity))) {
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

    protected function getNotificationRule(string $notificationProfileId, string $occurrence, string $entityType): ?RuleEntity
    {
        $key = $entityType . '_' . $occurrence . '_' . $notificationProfileId;
        if (isset($this->notificationRules[$key])) {
            return $this->notificationRules[$key];
        }

        $rule = $this->getNotificationRuleRepository()->findOneFromCache($notificationProfileId, $occurrence, $entityType);

        if (empty($rule)) {
            $rule = $this->getNotificationRuleRepository()->findOneFromCache($notificationProfileId, $occurrence, '');
        }

        return $this->notificationRules[$key] = $rule;
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

    protected function sendNotificationsRelationEntity(Entity $entity, string $occurrence): void
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

        $entityType1 = $this->relationEntityData[$entity->getEntityType()]['entity1'];

        if (!$this->notificationDisabled($entityType1) && $this->hasExistingRule($occurrence, $entityType1)) {
            $relatedEntity = $this->getEntityManager()->getEntity(
                $entityType1,
                $entity->get($this->relationEntityData[$entity->getEntityType()]['field1'])
            );
            $this->sendNotifications(
                $occurrence,
                $relatedEntity,
                [
                    "entities" => [
                        [
                            "name" => $name,
                            "entityId" => $entity->get($this->relationEntityData[$entity->getEntityType()]['field2']),
                            "entityType" => $this->relationEntityData[$entity->getEntityType()]['entity2'],
                        ],
                    ],
                ]
            );
        }

        $entityType2 = $this->relationEntityData[$entity->getEntityType()]['entity2'];

        if (!$this->notificationDisabled($entityType2) && $this->hasExistingRule($occurrence, $entityType2)) {
            $relatedEntity = $this->getEntityManager()->getEntity(
                $entityType2,
                $entity->get($this->relationEntityData[$entity->getEntityType()]['field2'])
            );

            $this->sendNotifications(
                $occurrence,
                $relatedEntity,
                [
                    "entities" => [
                        [
                            "name" => $name,
                            "entityId" => $entity->get($this->relationEntityData[$entity->getEntityType()]['field1']),
                            "entityType" => $this->relationEntityData[$entity->getEntityType()]['entity1'],
                        ],
                    ],
                ]
            );
        }
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

    protected function hasExistingRule(string $occurrence, string $entityType): bool
    {
        if (empty($this->getMetadata()->get(['app', 'activeNotificationProfilesIds']))) {
            return false;
        }

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

    protected function sendNoteNotifications(string $occurrence, Entity $entity): void
    {
        $this->sendNotifications(
            $occurrence,
            $entity,
            [
                "entities" => [
                    [
                        "name" => "parent",
                        "entityType" => $entity->get('parentType'),
                        "entityId" => $entity->get('parentId')
                    ]
                ]
            ]
        );
    }

    protected function getTeamIds(Entity $entity): array
    {
        $key = $entity->getEntityType() . '-' . $entity->get('id');

        if (!empty($this->teamMembers[$key])) {
            return $this->teamMembers[$key];
        }

        if(!$entity->hasRelation('teams') || !$entity->hasAttribute('teamsIds')){
            return [];
        }

        $teamsIds = $entity->getLinkMultipleIdList('teams');

        return $this->teamMembers[$key] = $teamsIds;
    }

    protected function getUpdateData(Entity $entity): ?array
    {
        $data = $this->getNoteUtil()->getChangedFieldsData($entity);

        if(empty($data['fields']) || empty($data['attributes']['was']) || empty($data['attributes']['became'])) {
            return null;
        }

        $container = (new \Atro\Core\Application())->getContainer();
        $auth = new \Espo\Core\Utils\Auth($container);
        $auth->useNoAuth();

        $data = json_decode(json_encode($data));

        $tmpEntity = $this->getEntityManager()->getEntity('Note');

        $container->get('serviceFactory')->create('Stream')->handleChangedData($data, $tmpEntity, $entity->getEntityType());

        $data = json_decode(json_encode($data), true);

        foreach ($tmpEntity->get('fieldDefs') as $key => $fieldDefs) {
            if(!empty($fieldDefs['type'])){
                $data['fieldTypes'][$key] = $fieldDefs['type'];
            }
            if($fieldDefs['type'] == 'link'){
                $data['linkDefs'][$key] = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $key]);
            }
        }

        $data['diff'] = $tmpEntity->get('diff');
        $data['fieldDefs'] = $tmpEntity->get('fieldDefs');
        sort($data['fields']);

        return $data;
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

    protected function getMemoryStorage(): MemoryStorage
    {
        return $this->container->get('memoryStorage');
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }
    protected function getNoteUtil(): NoteUtil
    {
        return $this->container->get(NoteUtil::class);
    }
}