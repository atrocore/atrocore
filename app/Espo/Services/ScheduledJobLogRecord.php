<?php

namespace Espo\Services;

class ScheduledJobLogRecord extends Record
{
    public function deleteOld(): bool
    {
        $days = $this->getConfig()->get('scheduledJobLogsMaxDays', 21);
        if ($days === 0) {
            return true;
        }

        // delete
        while (true) {
            $toDelete = $this->getEntityManager()->getRepository('ScheduledJobLogRecord')
                ->where(['executionTime<' => (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s')])
                ->limit(0, 2000)
                ->order('executionTime')
                ->find();
            if (empty($toDelete[0])) {
                break;
            }
            foreach ($toDelete as $entity) {
                $this->getEntityManager()->removeEntity($entity);
            }
        }

        // delete forever
        $daysToDeleteForever = $days + $this->getConfig()->get('scheduledJobLogsDeletedMaxDays', 14);
        $connection = $this->getEntityManager()->getConnection();
        $connection->createQueryBuilder()
            ->delete('scheduled_job_log_record')
            ->where('execution_time < :executeTime')
            ->setParameter('executeTime', (new \DateTime())->modify("-$daysToDeleteForever days")->format('Y-m-d H:i:s'))
            ->executeStatement();

        return true;
    }
}
