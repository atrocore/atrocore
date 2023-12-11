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

namespace Espo\Jobs;

use Atro\Core\EventManager\Manager;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Schema\Column;
use Espo\Core\EventManager\Event;
use Espo\Core\Jobs\Base;
use Espo\Core\Utils\Util;

class DeleteForever extends Base
{
    private string $date;

    public function run($data = null, $targetId = null, $targetType = null, $scheduledJobId = null): bool
    {
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $scheduledJobId ?? 'DeleteForever');

        if (empty($scheduledJob)) {
            return true;
        }

        $this->date = (new \DateTime())->modify("-{$scheduledJob->get('minimum_age')} day")->format('Y-m-d');
        $this->cleanupJobs();
        $this->cleanupScheduledJobLog();
        $this->cleanupAuthLog();
        $this->cleanupActionHistory();
        $this->cleanupAttachments();
        $this->cleanupDeleted();
        $this->cleanupDbSchema();
        $this->cleanupEntityTeam();

        $this->getEventManager()->dispatch('DeleteForeverJob', 'run', new Event());

        return true;
    }

    protected function cleanupEntityTeam()
    {
        $connection = $this->getEntityManager()->getConnection();
        foreach ($this->getMetadata()->get('entityDefs', []) as $scope => $data) {
            try {
                $connection->createQueryBuilder()
                    ->delete('entity_team')
                    ->where('entity_type = :entityType AND entity_id NOT IN (SELECT id FROM ' . $connection->quoteIdentifier(Util::toUnderScore($scope)) . ' WHERE deleted=:false)')
                    ->setParameter('entityType', $scope)
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * Cleanup jobs
     */
    protected function cleanupJobs(): void
    {
        $statuses = ['Success', 'Failed'];

        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('job'), 'j')
            ->where('DATE(j.execute_time) < :executeTime')
            ->andWhere('j.status IN (:statuses)')
            ->setParameter('executeTime', $this->date)
            ->setParameter('statuses', $statuses, Mapper::getParameterType($statuses))
            ->executeQuery();
    }

    /**
     * Cleanup scheduled job logs
     */
    protected function cleanupScheduledJobLog(): void
    {
        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('scheduled_job_log_record'), 'j')
            ->where('DATE(j.execution_time) < :executeTime')
            ->setParameter('executeTime', $this->date)
            ->executeQuery();
    }

    /**
     * Cleanup deleted
     */
    protected function cleanupDeleted(): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $tables = $connection->createSchemaManager()->listTableNames();
        foreach ($tables as $table) {
            if($table == 'attachment'){
                continue 1;
            }

            $columns = $connection->createSchemaManager()->listTableColumns($table);
            $columnNames = array_map(function(Column  $table){
                 return $table->getName();
            }, $columns);

            if (!in_array('deleted', $columnNames)) {
                continue 1;
            }

            $qb = $connection->createQueryBuilder()
                ->delete($connection->quoteIdentifier($table), 't')
                ->where('t.deleted = :true')
                ->setParameter('true', true, Mapper::getParameterType(true));;

            if(in_array('created_at', $columnNames) && !in_array('modified_at', $columnNames)){
               $qb->andWhere('DATE(t.created_at) < :date')
                   ->setParameter('date', $this->date);
            }

            if(!in_array('created_at', $columnNames) && in_array('modified_at', $columnNames)){
               $qb->andWhere('DATE(t.modified_at) < :date')
                   ->setParameter('date', $this->date);
            }

            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
                var_dump("Delete forever: {$e->getMessage()}");
            }
        }
    }

    /**
     * Cleanup auth log
     */
    protected function cleanupAuthLog(): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('auth_log_record'), 't')
            ->where('DATE(t.created_at) < :date')
            ->setParameter('date', $this->date)
            ->executeQuery();
    }

    /**
     * Cleanup action history
     */
    protected function cleanupActionHistory(): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('action_history_record'), 't')
            ->where('DATE(t.created_at) < :date')
            ->setParameter('date', $this->date)
            ->executeQuery();
    }

    /**
     * Cleanup attachments
     *
     */
    protected function cleanupAttachments(): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $fileManager = $this->getContainer()->get('fileStorageManager');
        $repository = $this->getEntityManager()->getRepository('Attachment');
        $attachments = $repository
            ->where([
                'deleted' => 1,
                'createdAt<=' => $this->date
            ])
            ->find(["withDeleted" => true]);
        foreach ($attachments as $entity){
            $fileManager->unlink($entity);
        }

        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('attachment'), 't')
            ->where('DATE(t.created_at) < :date')
            ->andWhere('t.deleted = :deleted')
            ->setParameter('date', $this->date)
            ->setParameter('deleted', true,  Mapper::getParameterType(true))
            ->executeQuery();
    }

    /**
     * Cleanup DB schema
     */
    protected function cleanupDbSchema(): void
    {
        try {
            $queries = $this->getContainer()->get('schema')->getDiffQueries();
        } catch (\Throwable $e) {
            $queries = [];
        }

        foreach ($queries as $query) {
            $this->exec($query);
        }
    }

    /**
     * @param string $sql
     */
    protected function exec(string $sql): void
    {
        try {
            $this->getEntityManager()->getPDO()->exec($sql);
        } catch (\PDOException $e) {
            $GLOBALS['log']->error('DeleteForever: ' . $e->getMessage() . ' | ' . $sql);
        }
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }
}
