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
        return $this
            ->getEntityManager()
            ->getRepository('Job')
            ->where([
                'status'        => CronManager::PENDING,
                'executeTime<=' => date('Y-m-d H:i:s')
            ])
            ->find();
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
    public function getJobByScheduledJob(string $scheduledJobId, string $date): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $qb = $connection->createQueryBuilder()
            ->select('j.*')
            ->from($connection->quoteIdentifier('job'), 'j')
            ->where("j.scheduled_job_id = :scheduledJobId")
            ->setParameter('scheduledJobId', $scheduledJobId)
            ->andWhere('j.execute_time >= :from')
            ->setParameter('from', (new \DateTime($date))->format('Y-m-d H:i:00'))
            ->andWhere('j.execute_time < :to')
            ->setParameter('to', (new \DateTime($date))->modify('+1 minute')->format('Y-m-d H:i:00'))
            ->andWhere('j.deleted = :deleted')
            ->setParameter('deleted', false, Mapper::getParameterType(false))
            ->setMaxResults(1);

        $scheduledJob = $qb->fetchAssociative();

        return empty($scheduledJob) ? [] : $scheduledJob;
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
                ->set("{$connection->quoteIdentifier('status')}", ':status')
                ->set("attempts", 0)
                ->where('j.id IN (:ids)')
                ->setParameter('status', CronManager::FAILED)
                ->setParameter('ids', $jobQuotedIdList, Mapper::getParameterType($jobQuotedIdList))
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
                    ->where('id IN (:ids)')
                    ->setParameter('deleted', true, Mapper::getParameterType(true))
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
                    ->set('status', ':status')
                    ->set('attempts', ':attempts')
                    ->set('failed_attempts', ':failedAttempts')
                    ->where('id = :id')
                    ->setParameter('status', CronManager::PENDING)
                    ->setParameter('attempts', $attempts, Mapper::getParameterType($attempts))
                    ->setParameter('failedAttempts', $failedAttempts, Mapper::getParameterType($failedAttempts))
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