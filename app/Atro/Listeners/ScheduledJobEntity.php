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
use Espo\Core\CronManager;

class ScheduledJobEntity extends AbstractListener
{
    public function afterCreateJobsFromScheduledJobs(Event $event): void
    {
        if ($this->getConfig()->get('notificationsMaxDays') !== 0) {
            $this->createJob('Delete Notifications', '20 1 * * *', 'Notification', 'deleteOld');
        }
        if ($this->getConfig()->get('queueItemsMaxDays') !== 0) {
            $this->createJob('Delete Queue Items', '42 1 * * *', 'QueueItem', 'deleteOld');
        }
    }

    public function createJob(string $name, string $scheduling, string $serviceName, string $methodName): void
    {
        $cronExpression = \Cron\CronExpression::factory($scheduling);
        $nextDate = $cronExpression->getNextRunDate()->format('Y-m-d H:i:s');

        $existingJob = $this->getEntityManager()->getRepository('Job')
            ->where([
                'serviceName' => $serviceName,
                'methodName'  => $methodName,
                'executeTime' => $nextDate,
            ])
            ->findOne();

        if (!empty($existingJob)) {
            return;
        }

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'        => $name,
            'status'      => CronManager::PENDING,
            'serviceName' => $serviceName,
            'methodName'  => $methodName,
            'executeTime' => $nextDate
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);
    }
}
