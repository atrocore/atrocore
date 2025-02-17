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

class Layout extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterGetLayoutContent(Event $event)
    {
        if ($event->getArgument('params')['viewType'] === 'leftSidebar') {
            if (empty($event->getArgument('params')['isCustom'])) {
                $scope = $event->getArgument('params')['scope'];

                // add self is entity is hierarchy type
                if ($this->getMetadata()->get(['scopes', $scope, 'type']) === 'Hierarchy'
                    && empty($this->getMetadata()->get(['scopes', $scope, 'disableHierarchy'], false))) {
                    $result = $event->getArgument('result');
                    $exists = false;
                    foreach ($result as $item) {
                        if ($item['name'] === '_self') {
                            $exists = true;
                        };
                    }
                    if (empty($exists)) {
                        $result[] = ['name' => '_self'];
                    }
                    $event->setArgument('result', $result);
                }

                // add bookmark if  activated
                if (empty($this->getMetadata()->get(['scopes', $scope, 'bookmarkDisabled']))) {
                    $result = $event->getArgument('result');
                    $result[] = ['name' => '_bookmark'];
                    $event->setArgument('result', $result);
                }
            }
        }

        $this->getEventManager()->dispatch($event->getArgument('target'), $event->getArgument('params')['viewType'], $event);
    }


    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }
}
