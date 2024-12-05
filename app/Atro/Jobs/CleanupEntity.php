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
        $entityName = $job->get('payload')->entityName ?? null;
        if (empty($entityName)) {
            return;
        }

        try {
            $this->getEntityManager()->getRepository($entityName)->cleanupDeletedRecords();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("Cleanup Entity failed for $entityName: {$e->getMessage()}");
        }

        //        if ($this->getConfig()->get('queueItemsMaxDays') !== 0) {
        //            $this->createJob('Delete Queue Items', '42 1 * * 0', 'QueueItem', 'deleteOld');
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
