<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\EventManager\Event;

/**
 * Class Controller
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Controller extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeAction(Event $event)
    {
        $this
            ->getContainer()
            ->get('eventManager')
            ->dispatch($event->getArgument('controller') . 'Controller', $event->getArgument('action'), $event);
    }

    /**
     * @param Event $event
     */
    public function afterAction(Event $event)
    {
        $this
            ->getContainer()
            ->get('eventManager')
            ->dispatch($event->getArgument('controller') . 'Controller', $event->getArgument('action'), $event);
    }
}
