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

class ActionLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        if ($this->isRelatedLayout($event)) {
            $result = $event->getArgument('result');

            $result[0]['rows'][] = [['name' => 'ActionSetLinker__sortOrder'], ['name' => 'ActionSetLinker__isActive']];

            $event->setArgument('result', $result);
        }
    }

    public function list(Event $event): void
    {
        if ($this->isRelatedLayout($event)) {
            $result = $event->getArgument('result');

            $result[] = ['name' => 'ActionSetLinker__isActive'];

            $event->setArgument('result', $result);
        }
    }

    public function relationships(Event $event): void
    {
        $result = $event->getArgument('result');

        $result[] = ['name' => 'actions'];

        $event->setArgument('result', $result);
    }
}
