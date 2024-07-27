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
use Atro\Core\Utils\Note as NoteUtil;
use Atro\Core\Utils\NotificationManager;
use Atro\NotificationTransport\NotificationOccurrence;

class Entity extends AbstractListener
{
    public function beforeSave(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeSave', $event);
    }

    public function afterSave(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterSave', $event);

        $this->getNoteUtil()->afterEntitySaved($entity = $event->getArgument('entity'));

        $occurrence = $entity->isNew() ? NotificationOccurrence::CREATION : NotificationOccurrence::UPDATE;
//        $this->getNotificationManager()->handleNotificationByJob($occurrence, $entity);
    }

    public function beforeRemove(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRemove', $event);

        $this->getNotificationManager()->handleNotificationByJob(NotificationOccurrence::DELETION, $event->getArgument('entity'));
    }

    public function afterRemove(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterRemove', $event);

        $this->getNoteUtil()->afterEntityRemoved($event->getArgument('entity'));
    }

    public function beforeMassRelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeMassRelate', $event);
    }

    public function afterMassRelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterMassRelate', $event);
    }

    public function beforeRelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRelate', $event);
    }

    public function afterRelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterRelate', $event);
    }

    public function beforeUnrelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'beforeUnrelate', $event);
    }

    public function afterUnrelate(Event $event): void
    {
        $this->getEventManager()->dispatch($event->getArgument('entityType') . 'Entity', 'afterUnrelate', $event);
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }

    private function getNoteUtil(): NoteUtil
    {
        return $this->getContainer()->get(NoteUtil::class);
    }

    protected function getNotificationManager(): NotificationManager
    {
        return $this->getContainer()->get(NotificationManager::class);
    }
}
