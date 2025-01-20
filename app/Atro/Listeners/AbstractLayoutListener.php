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
use Espo\Core\Utils\Json;
use Atro\Core\Utils\Util;

abstract class AbstractLayoutListener extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterGetLayoutContent(Event $event)
    {
        /** @var string $scope */
        $scope = $event->getArgument('params')['scope'];

        /** @var string $name */
        $name = $event->getArgument('params')['name'];

        $method = 'modify' . $scope . ucfirst($name);

        if (method_exists($this, $method)) {
            $this->{$method}($event);
        }
    }

    public function isCustomLayout(Event $event): bool
    {
        return $event->getArgument('params')['isCustom'];
    }

    public function isAdminPage(Event $event): bool
    {
        return $event->getArgument('params')['isAdminPage'];
    }
}
