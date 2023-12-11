<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Espo\ORM\EntityCollection;

class ScheduledJobEntity extends AbstractListener
{
    public function afterGetActiveScheduledJobList(Event $event): void
    {
        /** @var EntityCollection $collection */
        $collection = $event->getArgument('collection');

        if (empty($this->getConfig()->get('keepNotifications'))) {
            $job = $this->getEntityManager()->getEntity('ScheduledJob');
            $job->id = 'delete_notifications';
            $job->set('scheduling', '20 1 * * *');
            $job->set('job', 'DeleteNotifications');
            $job->set('name', 'Delete Notifications');
            $collection->append($job);
        }
    }
}
