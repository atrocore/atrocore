<?php

namespace Espo\Core\Utils\Cron;
use \PDO;
use \Espo\Core\Utils\Config;
use \Espo\Core\ORM\EntityManager;

class ScheduledJob
{
    private $config;

    private $entityManager;

    public function __construct(Config $config, EntityManager $entityManager)
    {
        $this->config = $config;
        $this->entityManager = $entityManager;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * Get active Scheduler Job List
     *
     * @return EntityCollection
     */
    public function getActiveScheduledJobList()
    {
        return $this->getEntityManager()->getRepository('ScheduledJob')->select([
            'id', 'scheduling', 'job', 'name'
        ])->where([
            'status' => 'Active'
        ])->find();
    }

    /**
     * Add record to ScheduledJobLogRecord about executed job
     *
     * @param string $scheduledJobId
     * @param string $status
     *
     * @return string ID of created ScheduledJobLogRecord
     */
    public function addLogRecord($scheduledJobId, $status, $runTime = null, $targetId = null, $targetType = null)
    {
        if (!isset($runTime)) {
            $runTime = date('Y-m-d H:i:s');
        }

        $entityManager = $this->getEntityManager();

        $scheduledJob = $entityManager->getEntity('ScheduledJob', $scheduledJobId);

        if (!$scheduledJob) {
            return;
        }

        $scheduledJob->set('lastRun', $runTime);
        $entityManager->saveEntity($scheduledJob, ['silent' => true]);

        $scheduledJobLog = $entityManager->getEntity('ScheduledJobLogRecord');
        $scheduledJobLog->set(array(
            'scheduledJobId' => $scheduledJobId,
            'name' => $scheduledJob->get('name'),
            'status' => $status,
            'executionTime' => $runTime,
            'targetId' => $targetId,
            'targetType' => $targetType
        ));
        $scheduledJobLogId = $entityManager->saveEntity($scheduledJobLog);

        return $scheduledJobLogId;
    }
}