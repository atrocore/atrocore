<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Hooks\Note;
use Treo\Core\EventManager\Event;

/**
 * Class NoteEntity
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class NoteEntity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeSave(Event $event)
    {
        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Note\Mentions::class)
                ->beforeSave($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Note\Notifications::class)
                ->afterSave($event->getArgument('entity'), $event->getArgument('options'));
        }
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
}
