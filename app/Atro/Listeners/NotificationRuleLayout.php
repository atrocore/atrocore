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

class NotificationRuleLayout extends AbstractLayoutListener
{
    protected function detail(Event $event): void
    {
        $result = $event->getArgument('result');

        $rows = [];

        foreach (array_keys(($this->getMetadata()->get(['app', 'notificationTransports'], []))) as $transport) {
            $rows[] = [["name" => $transport . 'Active'], ["name" => $transport . 'TemplateId']];
        }

        $result[] = [
            "label" => "Transport",
            "rows"  => $rows
        ];

        $event->setArgument('result',  $result);
    }
}
