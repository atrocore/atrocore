<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Hooks\Common;
use Treo\Core\EventManager\Event;

/**
 * Class Entity
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Entity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeSave(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeSave', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\CurrencyConverted::class)
                ->beforeSave($event->getArgument('entity'), $event->getArgument('options'));
            $this
                ->createHook(Common\Formula::class)
                ->beforeSave($event->getArgument('entity'), $event->getArgument('options'));
            $this
                ->createHook(Common\NextNumber::class)
                ->beforeSave($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterSave', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\AssignmentEmailNotification::class)
                ->afterSave($event->getArgument('entity'), $event->getArgument('options'));
            $this
                ->createHook(Common\Notifications::class)
                ->afterSave($event->getArgument('entity'), $event->getArgument('options'));
            $this
                ->createHook(Common\Stream::class)
                ->afterSave($event->getArgument('entity'), $event->getArgument('options'));
            $this
                ->createHook(Common\StreamNotesAcl::class)
                ->afterSave($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param Event $event
     */
    public function beforeRemove(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRemove', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\Notifications::class)
                ->beforeRemove($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterRemove', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\Notifications::class)
                ->afterRemove($event->getArgument('entity'), $event->getArgument('options'));
            $this
                ->createHook(Common\Stream::class)
                ->afterRemove($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param Event $event
     */
    public function beforeMassRelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeMassRelate', $event);
    }

    /**
     * @param Event $event
     */
    public function afterMassRelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterMassRelate', $event);
    }

    /**
     * @param Event $event
     * @throws \Espo\Core\Exceptions\Error
     */
    public function beforeRelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRelate', $event);

        //for move multiple attachments
        if ($this->isMultipleAttachment($event)) {
            $attachment = $this->getEntityManager()
                               ->getEntity("Attachment", $event->getArgument("foreign"));
            if ($attachment) {
                $this->getService("Attachment")->moveMultipleAttachment($attachment);
            }
        }
    }

    /**
     * @param Event $event
     */
    public function afterRelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterRelate', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\Stream::class)
                ->afterRelate(
                    $event->getArgument('entity'),
                    $event->getArgument('options'),
                    $this->getHookRelationData($event)
                );
        }
    }

    /**
     * @param Event $event
     */
    public function beforeUnrelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeUnrelate', $event);
    }

    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterUnrelate', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\Stream::class)
                ->afterUnrelate(
                    $event->getArgument('entity'),
                    $event->getArgument('options'),
                    $this->getHookRelationData($event)
                );
        }
    }

    /**
     * @param string $target
     * @param string $action
     * @param Event  $event
     */
    protected function dispatch(string $target, string $action, Event $event)
    {
        $this->getContainer()->get('eventManager')->dispatch($target, $action, $event);
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
        $foreignEntityName = $this
            ->getContainer()
            ->get('metadata')
            ->get(['entityDefs', $entity, 'links', $relationName, 'entity']);

        return (!empty($foreignEntityName)) ? $this->getEntityManager()->getEntity($foreignEntityName, $id) : null;
    }

    /**
     * @param Event $event
     *
     * @return array
     */
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

    /**
     * @param Event $event
     * @return bool
     */
    private function isMultipleAttachment(Event $event)
    {
        $metaData = $this->getMetadata()
                         ->get([
                             "entityDefs",
                             $event->getArgument("entityType"),
                             "links",
                             $event->getArgument("relationName"),
                         ]);

        if ($metaData['type'] === "hasChildren" && $metaData['entity'] === "Attachment") {
            return true;
        }

        return false;
    }
}
