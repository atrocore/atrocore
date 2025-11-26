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
        if ($event->getArgument('params')['viewType'] === 'navigation') {
            if (empty($event->getArgument('params')['isCustom'])) {
                $scope = $event->getArgument('params')['scope'];

                if ($scope === 'Settings' && $this->getUser()->isAdmin()) {
                    $result[] = ['name' => '_admin'];
                    $event->setArgument('result', $result);
                }
                // add admin navigation
                if ($this->isAdminView($scope) && $this->getUser()->isAdmin()) {
                    $result[] = ['name' => '_admin'];
                    $event->setArgument('result', $result);
                }

                // add _self if entity is hierarchy type
                if (in_array($this->getMetadata()->get(['scopes', $scope, 'type']), ['Hierarchy', 'Base'])) {
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

                // add _bookmark if  activated
                if (empty($this->getMetadata()->get(['scopes', $scope, 'bookmarkDisabled']))) {
                    $result = $event->getArgument('result');
                    $result[] = ['name' => '_bookmark'];
                    $event->setArgument('result', $result);
                }
            }
        }

        if ($event->getArgument('params')['viewType'] === 'selection') {
            $result = $event->getArgument('result');
            if (empty($event->getArgument('params')['isCustom']) && empty($result)) {
                $scope = $event->getArgument('params')['scope'];
                $result = [];

                $scopeDefs = $this->getMetadata()->get(['scopes', $scope]);

                if (!empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', 'name', 'type']))) {
                    $result[] = ["name" => "name"];
                } else {
                    $result[] = ["name" => "id"];
                }

                $event->setArgument('result', $result);
            }
        }

        if ($event->getArgument('params')['viewType'] === 'insights') {
            $result = $event->getArgument('result');
            $scope = $event->getArgument('params')['scope'];

            if (empty($event->getArgument('params')['isCustom'])) {
                if (empty($result)){
                    $result = [['name' => 'summary'], ['name' => 'accessManagement']];
                    $event->setArgument('result', $result);
                }

                if (in_array('matchedRecords', array_column($this->getMetadata()->get(['clientDefs', $scope, 'rightSidePanels']) ?? [], 'name'))) {
                    $result[] = ['name' => 'matchedRecords'];
                    $event->setArgument('result', $result);
                }
            }
        }

        $this->getEventManager()->dispatch($event->getArgument('target'), $event->getArgument('params')['viewType'], $event);
    }

    protected function isAdminView($scope): bool
    {
        foreach ($this->getMetadata()->get(['app', 'adminPanel']) as $panel) {
            foreach ($panel['itemList'] as $item) {
                if (!empty($item['url']) && (str_starts_with($item['url'], "#$scope/") || $item['url'] === "#$scope")) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }
}
