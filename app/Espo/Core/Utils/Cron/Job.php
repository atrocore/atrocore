<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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

namespace Espo\Core\Utils\Cron;

use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\CronManager;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\System;

class Job
{
    private $config;

    private $entityManager;

    private $cronScheduledJob;

    public function __construct(Config $config, EntityManager $entityManager)
    {
        $this->config = $config;
        $this->entityManager = $entityManager;

        $this->cronScheduledJob = new ScheduledJob($this->config, $this->entityManager);
    }

    protected function getConfig()
    {
        return $this->config;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    protected function getCronScheduledJob()
    {
        return $this->cronScheduledJob;
    }

    public function isJobPending($id)
    {
        return !!$this->getEntityManager()->getRepository('Job')->select(['id'])->where([
            'id'     => $id,
            'status' => CronManager::PENDING
        ])->findOne();
    }

    public function getPendingJobList()
    {
        $limit = intval($this->getConfig()->get('jobMaxPortion', 0));

        $selectParams = [
            'select'      => [
                'id',
                'scheduledJobId',
                'scheduledJobJob',
                'executeTime',
                'targetId',
                'targetType',
                'methodName',
                'method', // TODO remove deprecated
                'serviceName',
                'data'
            ],
            'whereClause' => [
                'status'        => CronManager::PENDING,
                'executeTime<=' => date('Y-m-d H:i:s')
            ],
            'orderBy'     => 'executeTime'
        ];
        if ($limit) {
            $selectParams['offset'] = 0;
            $selectParams['limit'] = $limit;
        }

        return $this->getEntityManager()->getRepository('Job')->find($selectParams);
    }

    public function isScheduledJobRunning($scheduledJobId, $targetId = null, $targetType = null)
    {
        $where = [
            'scheduledJobId' => $scheduledJobId,
            'status'         => CronManager::RUNNING
        ];
        if ($targetId && $targetType) {
            $where['targetId'] = $targetId;
            $where['targetType'] = $targetType;
        }
        return !!$this->getEntityManager()->getRepository('Job')->select(['id'])->where($where)->findOne();
    }

    public function getRunningScheduledJobIdList()
    {
        $list = [];

        $connection = $this->getEntityManager()->getConnection();
        $rowList = $connection->createQueryBuilder()
            ->select('j.scheduled_job_id')
            ->from($connection->quoteIdentifier('job'), 'j')
            ->where("j.status = :status")
            ->setParameter('status', CronManager::RUNNING)
            ->andWhere('j.scheduled_job_id IS NOT NULL')
            ->andWhere('j.target_id IS NULL')
            ->andWhere('j.deleted = :deleted')
            ->setParameter('deleted', false, Mapper::getParameterType(false))
            ->orderBy('j.execute_time', 'ASC')
            ->fetchAllAssociative();

        foreach ($rowList as $row) {
            $list[] = $row['scheduled_job_id'];
        }

        return $list;
    }

    /**
     * Get Jobs by ScheduledJobId and date
     *
     * @param string $scheduledJobId
     * @param string $time
     *
     * @return array
     */
    public function getJobByScheduledJob($scheduledJobId, $time)
    {
        $dateObj = new \DateTime($time);
        $timeWithoutSeconds = $dateObj->format('Y-m-d H:i:');

        $connection = $this->getEntityManager()->getConnection();
        $scheduledJob = $connection->createQueryBuilder()
            ->select('j.*')
            ->from($connection->quoteIdentifier('job'), 'j')
            ->where("j.scheduled_job_id = :scheduledJobId")
            ->setParameter('scheduledJobId', $scheduledJobId)
            ->andWhere('j.execute_time LIKE :timeWithoutSeconds')
            ->setParameter('timeWithoutSeconds', $timeWithoutSeconds . '%')
            ->andWhere('j.deleted = :deleted')
            ->setParameter('deleted', false, Mapper::getParameterType(false))
            ->setMaxResults(1)
            ->fetchAssociative();

        return $scheduledJob;
    }

    /**
     * Mark pending jobs (all jobs that exceeded jobPeriod)
     *
     * @return void
     */
    public function markFailedJobs()
    {
        $this->markFailedJobsByPeriod('jobPeriodForActiveProcess');
        $this->markFailedJobsByPeriod('jobPeriod');
    }

    protected function markFailedJobsByPeriod($period)
    {
        $time = time() - $this->getConfig()->get($period);

        $connection = $this->getEntityManager()->getConnection();
        $rows = $connection->createQueryBuilder()
            ->select('j.*')
            ->from($connection->quoteIdentifier('job'), 'j')
            ->where("j.status = :status")
            ->setParameter('status', CronManager::RUNNING)
            ->andWhere('j.execute_time < :executeTime')
            ->setParameter('executeTime', date('Y-m-d H:i:s', $time))
            ->fetchAllAssociative();

        $pdo = $this->getEntityManager()->getPDO();

        $jobData = array();

        switch ($period) {
            case 'jobPeriod':
                foreach ($rows as $row) {
                    if (empty($row['pid']) || !System::isProcessActive($row['pid'])) {
                        $jobData[$row['id']] = $row;
                    }
                }
                break;

            case 'jobPeriodForActiveProcess':
                foreach ($rows as $row) {
                    $jobData[$row['id']] = $row;
                }
                break;
        }

        if (!empty($jobData)) {
            $jobQuotedIdList = [];
            foreach ($jobData as $jobId => $job) {
                $jobQuotedIdList[] = $pdo->quote($jobId);
            }

            $connection->createQueryBuilder()
                ->update($connection->quoteIdentifier('job'), 'j')
                ->set("j.{$connection->quoteIdentifier('status')}", CronManager::FAILED)
                ->set("j.attempts", 0)
                ->where('j.id IN (:ids)')
                ->setParameter('ids', $jobQuotedIdList, Mapper::getParameterType(PARAM_STR_ARRAY))
                ->executeQuery();

            $cronScheduledJob = $this->getCronScheduledJob();
            foreach ($jobData as $jobId => $job) {
                if (!empty($job['scheduled_job_id'])) {
                    $cronScheduledJob->addLogRecord($job['scheduled_job_id'], CronManager::FAILED, $job['execute_time'], $job['target_id'], $job['target_type']);
                }
            }
        }
    }

    /**
     * Remove pending duplicate jobs, no need to run twice the same job
     *
     * @return void
     */
    public function removePendingJobDuplicates()
    {
        $connection = $this->getEntityManager()->getConnection();

        $duplicateJobList = $connection->createQueryBuilder()
            ->select('j.scheduled_job_id')
            ->from($connection->quoteIdentifier('job'), 'j')
            ->where('j.scheduled_job_id IS NOT NULL')
            ->andWhere('j.status = :status')
            ->setParameter('status', CronManager::PENDING)
            ->andWhere('j.execute_time <= :executeTime')
            ->setParameter('executeTime', date('Y-m-d H:i:s'))
            ->andWhere('target_id IS NULL')
            ->andWhere('j.deleted = :deleted')
            ->setParameter('deleted', false, Mapper::getParameterType(false))
            ->groupBy('j.scheduled_job_id')
            ->orderBy('MAX(j.execute_time)', 'ASC')
            ->fetchAllAssociative();

        foreach ($duplicateJobList as $row) {
            if (!empty($row['scheduled_job_id'])) {
                $res = $connection->createQueryBuilder()
                    ->select('j.id')
                    ->from($connection->quoteIdentifier('job'), 'j')
                    ->where('j.scheduled_job_id = :scheduledJobId')
                    ->setParameter('scheduledJobId', $row['scheduled_job_id'], Mapper::getParameterType($row['scheduled_job_id']))
                    ->andWhere('j.status = :status')
                    ->setParameter('status', CronManager::PENDING)
                    ->orderBy('j.execute_time', 'ASC')
                    ->setFirstResult(1)
                    ->setMaxResults(100000)
                    ->fetchAllAssociative();

                $jobIdList = array_column($res, 'id');

                if (empty($jobIdList)) {
                    continue;
                }

                $connection->createQueryBuilder()
                    ->update($connection->quoteIdentifier('job'))
                    ->set('deleted', ':deleted')
                    ->setParameter('deleted', true, Mapper::getParameterType(true))
                    ->where('id IN (:ids)')
                    ->setParameter('ids', $jobIdList, Mapper::getParameterType($jobIdList))
                    ->executeQuery();
            }
        }
    }

    /**
     * Mark job attempts
     *
     * @return void
     */
    public function updateFailedJobAttempts()
    {
        $connection = $this->getEntityManager()->getConnection();

        $rows = $connection->createQueryBuilder()
            ->select('j.*')
            ->from($connection->quoteIdentifier('job'), 'j')
            ->where('j.status = :status')
            ->setParameter('status', CronManager::FAILED)
            ->andWhere('j.deleted = :deleted')
            ->setParameter('deleted', false, Mapper::getParameterType(false))
            ->andWhere('j.execute_time <= :executeTime')
            ->setParameter('executeTime', date('Y-m-d H:i:s'))
            ->andWhere('j.attempts > :attempts')
            ->setParameter('attempts', 0, Mapper::getParameterType(0))
            ->fetchAllAssociative();

        if ($rows) {
            foreach ($rows as $row) {
                $row['failed_attempts'] = isset($row['failed_attempts']) ? $row['failed_attempts'] : 0;

                $attempts = $row['attempts'] - 1;
                $failedAttempts = $row['failed_attempts'] + 1;

                $connection->createQueryBuilder()
                    ->update($connection->quoteIdentifier('job'), 'j')
                    ->set('j.status', ':status')
                    ->setParameter('status', CronManager::PENDING)
                    ->set('j.attempts', ':attempts')
                    ->setParameter('attempts', $attempts, Mapper::getParameterType($attempts))
                    ->set('j.failed_attempts', ':failedAttempts')
                    ->setParameter('failedAttempts', $failedAttempts, Mapper::getParameterType($failedAttempts))
                    ->where('id = :id)')
                    ->setParameter('id', $row['id'], Mapper::getParameterType($row['id']))
                    ->executeQuery();
            }
        }
    }

    public function getPid()
    {
        return System::getPid();
    }
}