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

/**
 * Class ActionHistoryRecordController
 */
class ActionHistoryRecordController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeActionList(Event $event)
    {
        // get where
        $where = $event->getArgument('request')->get('where', []);

        // get scopes
        $scopes = $this
            ->getMetadata()
            ->get('scopes');

        // prepare where
        $where[] = [
            'type'      => 'in',
            'attribute' => 'targetType',
            'value'     => array_keys($scopes)
        ];

        // set where
        $event->getArgument('request')->setQuery('where', $where);
    }
}
