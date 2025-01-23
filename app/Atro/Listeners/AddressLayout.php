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

class AddressLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        if ($this->getRelatedEntity($event) === 'Account') {
            $result = $event->getArgument('result');

            if (!str_contains(json_encode($result), '"AddressAccount__default"')) {
                $result[0]['rows'][] = [['name' => 'AddressAccount__default'], false];
            }

            $event->setArgument('result', $result);
        }
    }

    public function list(Event $event): void
    {
        if ($this->getRelatedEntity($event) === 'Account') {
            $result = $event->getArgument('result');

            if (!str_contains(json_encode($result), '"AddressAccount__default"')) {
                $result[] = ['name' => 'AddressAccount__default'];
            }

            $event->setArgument('result', $result);
        }
    }
}
