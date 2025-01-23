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
    public function isCustomLayout(Event $event): bool
    {
        return $event->getArgument('params')['isCustom'];
    }

    public function isAdminPage(Event $event): bool
    {
        return $event->getArgument('params')['isAdminPage'];
    }

    public function isRelatedLayout(Event $event): bool
    {
        return !empty($this->getRelatedEntity($event));
    }

    public function getRelatedEntity(Event $event): ?string
    {
        return $event->getArgument('params')['relatedEntity'];
    }
}
