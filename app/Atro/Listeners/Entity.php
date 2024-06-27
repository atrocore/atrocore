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

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager;
use Espo\Hooks\Common;
use Espo\ORM\Entity as OrmEntity;
use Espo\Services\Stream as StreamService;

class Entity extends AbstractListener
{
    private array $streamEnabled = [];
    private ?bool $followCreatedEntities = null;
    private ?StreamService $streamService = null;

    public function beforeSave(Event $event): void
    {
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeSave', $event);
    }

    public function afterSave(Event $event): void
    {
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterSave', $event);

        /** @var OrmEntity $entity */
        $entity = $event->getArgument('entity');

        if ($this->streamEnabled($entity)) {
            if ($entity->isNew()) {
                $this->followCreatedEntity($entity);
            }
        }

        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks']) && !$this->skipHooks()) {
            $this
                ->createHook(Common\Stream::class)
                ->afterSave($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    public function beforeRemove(Event $event): void
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRemove', $event);
    }

    public function afterRemove(Event $event): void
    {
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterRemove', $event);

        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks']) && !$this->skipHooks()) {
            $this
                ->createHook(Common\Stream::class)
                ->afterRemove($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    public function beforeMassRelate(Event $event): void
    {
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeMassRelate', $event);
    }

    public function afterMassRelate(Event $event): void
    {
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterMassRelate', $event);
    }

    public function beforeRelate(Event $event): void
    {
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRelate', $event);
    }

    public function afterRelate(Event $event): void
    {
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterRelate', $event);

        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks']) && !$this->skipHooks()) {
            $this
                ->createHook(Common\Stream::class)
                ->afterRelate(
                    $event->getArgument('entity'),
                    $event->getArgument('options'),
                    $this->getHookRelationData($event)
                );
        }
    }

    public function beforeUnrelate(Event $event): void
    {
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeUnrelate', $event);
    }

    public function afterUnrelate(Event $event): void
    {
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterUnrelate', $event);

        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks']) && !$this->skipHooks()) {
            $this
                ->createHook(Common\Stream::class)
                ->afterUnrelate(
                    $event->getArgument('entity'),
                    $event->getArgument('options'),
                    $this->getHookRelationData($event)
                );
        }
    }

    protected function dispatch(string $target, string $action, Event $event): void
    {
        /** @var Manager $eventManager */
        $eventManager = $this->getContainer()->get('eventManager');
        $eventManager->dispatch($target, $action, $event);
    }

    /**
     * @param string $className
     *
     * @return mixed
     */
    private function createHook(string $className)
    {
        $hook = new $className();
        foreach ($hook->getDependencyList() as $name) {
            $hook->inject($name, $this->getContainer()->get($name));
        }

        return $hook;
    }

    /**
     * @param string $entity
     * @param string $relationName
     * @param string $id
     *
     * @return mixed
     */
    private function findForeignEntity(string $entity, string $relationName, string $id)
    {
        $foreignEntityName = $this->getMetadata()->get(['entityDefs', $entity, 'links', $relationName, 'entity']);

        return (!empty($foreignEntityName)) ? $this->getEntityManager()->getEntity($foreignEntityName, $id) : null;
    }

    private function getHookRelationData(Event $event): array
    {
        // prepare foreign
        $foreign = $event->getArgument('foreign');
        if (is_string($foreign)) {
            $foreign = $this->findForeignEntity(
                $event->getArgument('entity')->getEntityType(),
                $event->getArgument('relationName'),
                $foreign
            );
        }

        return [
            'relationName'  => $event->getArgument('relationName'),
            'relationData'  => $event->getArgument('relationData'),
            'foreignEntity' => $foreign,
        ];
    }

    private function skipHooks(): bool
    {
        return !empty($this->getEntityManager()->getMemoryStorage()->get('skipHooks'));
    }

    protected function streamEnabled(OrmEntity $entity): bool
    {
        if (!isset($this->streamEnabled[$entity->getEntityType()])) {
            $this->streamEnabled[$entity->getEntityType()] = empty($this->getMetadata()->get("scopes.{$entity->getEntityType()}.streamDisabled"));
        }

        return $this->streamEnabled[$entity->getEntityType()];
    }

    protected function followCreatedEntity(OrmEntity $entity): void
    {
        $userIdList = [];
        if ($this->isFollowCreatedEntities() && $entity->get('createdById') && $entity->get('createdById') === $this->getUser()->id) {
            $userIdList[] = $entity->get('createdById');
        }

        if (!empty($entity->get('assignedUserId')) && !in_array($entity->get('assignedUserId'), $userIdList)) {
            $userIdList[] = $entity->get('assignedUserId');
        }

        if (!empty($userIdList)) {
            $this->getStreamService()->followEntityMass($entity, $userIdList);
        }

        if (in_array($this->getUser()->id, $userIdList)) {
            $entity->set('isFollowed', true);
        }
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
}
