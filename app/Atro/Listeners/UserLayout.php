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

class UserLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        if ($this->getRelatedEntity($event)==='Team') {
            $result = $event->getArgument('result');

            $result[0]['rows'][] = [['name' => 'TeamUser__role'], false];

            $event->setArgument('result', $result);
        }
    }
}
