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

namespace Atro\Jobs;

use Espo\ORM\Entity;

class CleanupEntity extends AbstractJob implements JobInterface
{
    public function run(Entity $job): void
    {
        $i = 0;
        while ($i < 10) {
            $i++;
            sleep(1);
        }



        // for all entities we have to check if we should delete something and if we should we create a job for it

        // if ($this->getConfig()->get('notificationsMaxDays') !== 0) {
        //            $this->createJob('Delete Notifications', '20 1 * * 0', 'Notification', 'deleteOld');
        //        }
        //        if ($this->getConfig()->get('queueItemsMaxDays') !== 0) {
        //            $this->createJob('Delete Queue Items', '42 1 * * 0', 'QueueItem', 'deleteOld');
        //        }
        //        if ($this->getConfig()->get('jobsMaxDays') !== 0) {
        //            $this->createJob('Delete Jobs', '0 2 * * 0', 'Job', 'deleteOld');
        //        }
        //        if ($this->getConfig()->get('authLogsMaxDays') !== 0) {
        //            $this->createJob('Delete Auth Logs', '40 2 * * 0', 'AuthLogRecord', 'deleteOld');
        //        }
        //        if ($this->getConfig()->get('actionHistoryMaxDays') !== 0) {
        //            $this->createJob('Delete Action History Records', '50 2 * * 0', 'ActionHistoryRecord', 'deleteOld');
        //        }
        //        if ($this->getConfig()->get('deletedItemsMaxDays') !== 0) {
        //            $this->createJob('Remove Deleted Items', '20 3 * * 0', 'App', 'cleanupDeleted');
        //        }
        //        if ($this->getConfig()->get('cleanDbSchema') !== false) {
        //            $this->createJob('Clean DB Schema', '50 3 * * 0', 'App', 'cleanDbSchema');
        //        }
        //        if ($this->getConfig()->get('cleanEntityTeam') !== false) {
        //            $this->createJob('Clean Entity Team', '0 4 * * 0', 'App', 'cleanupEntityTeam');
        //        }

        // $cronExpression = \Cron\CronExpression::factory($scheduling);
        //        $nextDate = $cronExpression->getNextRunDate()->format('Y-m-d H:i:s');
        //
        //        $existingJob = $this->getEntityManager()->getRepository('Job')
        //            ->where([
        //                'serviceName' => $serviceName,
        //                'methodName'  => $methodName,
        //                'executeTime' => $nextDate,
        //            ])
        //            ->findOne();
        //
        //        if (!empty($existingJob)) {
        //            return;
        //        }
        //
        //        $jobEntity = $this->getEntityManager()->getEntity('Job');
        //        $jobEntity->set([
        //            'name'        => $name,
        //            'status'      => 'Pending',
        //            'serviceName' => $serviceName,
        //            'methodName'  => $methodName,
        //            'executeTime' => $nextDate
        //        ]);
        //        $this->getEntityManager()->saveEntity($jobEntity);
    }
}
