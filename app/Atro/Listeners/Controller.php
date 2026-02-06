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
use Atro\Core\EventManager\Manager as EventManager;

class Controller extends AbstractListener
{
    public function beforeAction(Event $event): void
    {
        $this
            ->getEventManager()
            ->dispatch($event->getArgument('controller') . 'Controller', $event->getArgument('action'), $event);
    }

    public function afterAction(Event $event): void
    {
        $this
            ->getEventManager()
            ->dispatch($event->getArgument('controller') . 'Controller', $event->getArgument('action'), $event);
    }

    protected function getEventManager(): EventManager
    {
        return $this->getContainer()->get('eventManager');
    }
}
