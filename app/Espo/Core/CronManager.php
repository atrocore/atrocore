<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\Core;

use Atro\Core\Exceptions\Error;
use Espo\Core\EventManager\Event;
use Espo\Core\Utils\Json;
use Atro\Core\Exceptions\NotFound;
use Espo\ORM\Entity;

class CronManager extends Injectable
{
    const PENDING = 'Pending';

    const RUNNING = 'Running';

    const SUCCESS = 'Success';

    const FAILED = 'Failed';

    public function __construct()
    {
        $this->addDependency('container');
    }

    protected function getContainer()
    {
        return $this->getInjection('container');
    }

    protected function getConfig()
    {
        return $this->getContainer()->get('config');
    }

    protected function getFileManager()
    {
        return $this->getContainer()->get('fileManager');
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getServiceFactory()
    {
        return $this->getContainer()->get('serviceFactory');
    }

    protected function getScheduledJobUtil()
    {
        return $this->getContainer()->get('scheduledJob');
    }

    protected function getCronJobUtil()
    {
        return new \Espo\Core\Utils\Cron\Job($this->getConfig(), $this->getEntityManager());
    }

    protected function getCronScheduledJobUtil()
    {
        return new \Espo\Core\Utils\Cron\ScheduledJob($this->getConfig(), $this->getEntityManager());
    }

    protected function getLastRunTime()
    {
        $lastRunData = $this->getContainer()->get('dataManager')->getCacheData('cronLastRunTime');

        if (is_array($lastRunData) && !empty($lastRunData['time'])) {
            $lastRunTime = $lastRunData['time'];
        } else {
            $lastRunTime = time() - intval($this->getConfig()->get('cronMinInterval', 0)) - 1;
        }

        return $lastRunTime;
    }

    protected function setLastRunTime($time)
    {
        return $this->getContainer()->get('dataManager')->setCacheData('cronLastRunTime', ['time' => $time]);
    }

    protected function checkLastRunTime()
    {
        $currentTime = time();
        $lastRunTime = $this->getLastRunTime();
        $cronMinInterval = $this->getConfig()->get('cronMinInterval', 0);

        if ($currentTime > ($lastRunTime + $cronMinInterval)) {
            return true;
        }

        return false;
    }

    /**
     * Run Cron
     *
     * @return void
     */
    public function run()
    {
        if (!$this->checkLastRunTime()) {
            $GLOBALS['log']->info('CronManager: Stop cron running, too frequent execution.');
            return;
        }

        $this->setLastRunTime(time());

        $this->getCronJobUtil()->markFailedJobs();
        $this->getCronJobUtil()->updateFailedJobAttempts();
        $this->createJobsFromScheduledJobs();
        $this->getCronJobUtil()->removePendingJobDuplicates();

        $pendingJobList = $this->getCronJobUtil()->getPendingJobList();

        foreach ($pendingJobList as $job) {
            $skip = false;
            if ($this->getCronJobUtil()->isJobPending($job->id)) {
                if ($job->get('scheduledJobId')) {
                    if ($this->getCronJobUtil()->isScheduledJobRunning($job->get('scheduledJobId'), $job->get('targetId'), $job->get('targetType'))) {
                        $skip = true;
                    }
                }
            } else {
                $skip = true;
            }

            if ($skip) {
                continue;
            }

            $job->set('status', self::RUNNING);
            $job->set('pid', $this->getCronJobUtil()->getPid());
            $this->getEntityManager()->saveEntity($job);

            $isSuccess = true;
            $skipLog = false;

            try {
                if ($job->get('scheduledJobId')) {
                    $this->runScheduledJob($job);
                } else {
                    $this->runService($job);
                }
            } catch (\Exception $e) {
                $isSuccess = false;
                if ($e->getCode() === -1) {
                    $job->set('attempts', 0);
                    $skipLog = true;
                } else {
                    $GLOBALS['log']->error('CronManager: Failed job running, job ['.$job->id.']. Error Details: '.$e->getMessage());
                }
            }

            $status = $isSuccess ? self::SUCCESS : self::FAILED;

            $job->set('status', $status);
            $this->getEntityManager()->saveEntity($job);

            if ($job->get('scheduledJobId') && !$skipLog) {
                $this->getCronScheduledJobUtil()->addLogRecord($job->get('scheduledJobId'), $status, null, $job->get('targetId'), $job->get('targetType'));
            }
        }
    }

    protected function runScheduledJob($job)
    {
        $jobName = $job->get('scheduledJobJob');

        $className = $this->getScheduledJobUtil()->get($jobName);
        if ($className === false) {
            throw new NotFound();
        }

        $jobClass = new $className($this->getContainer());
        $method = 'run';
        if (!method_exists($jobClass, $method)) {
            throw new NotFound();
        }

        $data = null;

        if ($job->get('data')) {
            $data = $job->get('data');
        }

        $scheduledJob = $this->getEntityManager()->getRepository('ScheduledJob')->get($job->get('scheduledJobId'));
        if (!empty($scheduledJob) && $scheduledJob->get('status') === 'Active') {
            $jobClass->$method($data, $job->get('targetId'), $job->get('targetType'), $scheduledJob->get('id'));
        } else {
            throw new Error('ScheduledJob has been deleted or deactivated.');
        }
    }

    /**
     * Run Service
     *
     * @param Entity $job
     *
     * @return void
     */
    protected function runService($job)
    {
        $serviceName = $job->get('serviceName');

        if (!$serviceName) {
            throw new Error('Job with empty serviceName.');
        }

        if (!$this->getServiceFactory()->checkExists($serviceName)) {
            throw new NotFound();
        }

        $service = $this->getServiceFactory()->create($serviceName);

        $methodNameDeprecated = $job->get('method');
        $methodName = $job->get('methodName');

        $isDeprecated = false;
        if (!$methodName) {
            $isDeprecated = true;
            $methodName = $methodNameDeprecated;
        }

        if (!$methodName) {
            throw new Error('Job with empty methodName.');
        }

        if (!method_exists($service, $methodName)) {
            throw new NotFound();
        }

        $data = $job->get('data');

        if ($isDeprecated) {
            $data = Json::decode(Json::encode($data), true);
        }

        $service->$methodName($data, $job->get('targetId'), $job->get('targetType'));
    }

    protected function createJobsFromScheduledJobs()
    {
        $activeScheduledJobList = $this->getCronScheduledJobUtil()->getActiveScheduledJobList();

        $runningScheduledJobIdList = $this->getCronJobUtil()->getRunningScheduledJobIdList();

        $createdJobIdList = array();
        foreach ($activeScheduledJobList as $scheduledJob) {
            $scheduling = $scheduledJob->get('scheduling');

            try {
                $cronExpression = \Cron\CronExpression::factory($scheduling);
            } catch (\Exception $e) {
                $GLOBALS['log']->error('CronManager (ScheduledJob ['.$scheduledJob->id.']): Scheduling string error - '. $e->getMessage() . '.');
                continue;
            }

            try {
                $nextDate = $cronExpression->getNextRunDate()->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $GLOBALS['log']->error('CronManager (ScheduledJob ['.$scheduledJob->id.']): Unsupported CRON expression ['.$scheduling.']');
                continue;
            }

            $existingJob = $this->getCronJobUtil()->getJobByScheduledJob($scheduledJob->id, $nextDate);
            if ($existingJob) continue;

            $className = $this->getScheduledJobUtil()->get($scheduledJob->get('job'));
            if ($className) {
                if (method_exists($className, 'prepare')) {
                    $implementation = new $className($this->getContainer());
                    $implementation->prepare($scheduledJob, $nextDate);
                    continue;
                }
            }

            if (in_array($scheduledJob->id, $runningScheduledJobIdList)) {
                continue;
            }

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set(array(
                'name' => $scheduledJob->get('name'),
                'status' => self::PENDING,
                'scheduledJobId' => $scheduledJob->id,
                'executeTime' => $nextDate
            ));
            $this->getEntityManager()->saveEntity($jobEntity);
        }

        $this->getContainer()
            ->get('eventManager')
            ->dispatch('ScheduledJobEntity', 'afterCreateJobsFromScheduledJobs', new Event());
    }
}
