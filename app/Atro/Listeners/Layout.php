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
use Espo\Core\Utils\Json;
use Atro\Core\Utils\Util;

class Layout extends AbstractLayoutListener
{
    /**
     * @param Event $event
     */
    public function afterGetLayoutContent(Event $event)
    {
        $this->getEventManager()->dispatch($event->getArgument('target'), $event->getArgument('params')['viewType'], $event);
    }


    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }
}
