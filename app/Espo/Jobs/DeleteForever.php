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

declare(strict_types=1);

namespace Espo\Jobs;

use Atro\Core\EventManager\Manager;
use Atro\ORM\DB\RDB\Mapper;
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
        $this->cleanupNotifications();
        $this->cleanupDeleted();
        $this->cleanupAttachments();
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
            ->where('DATE(j.execute_time) < :executeTime')
            ->setParameter('executeTime', $this->date)
            ->executeQuery();
    }

    /**
     * Cleanup deleted
     */
    protected function cleanupDeleted(): void
    {
        $connection = $this->getEntityManager()->getConnection();
        foreach ($this->getMetadata()->get(['entityDefs'], []) as $entityType => $entityDefs) {
            $qb = $connection->createQueryBuilder()
                ->delete($connection->quoteIdentifier(Util::toUnderScore(lcfirst($entityType))), 't')
                ->where('DATE(t.modified_at) < :date')
                ->andWhere('t.deleted = :true')
                ->setParameter('date', $this->date)
                ->setParameter('true', true, Mapper::getParameterType(true));
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
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
     * Cleanup notifications
     */
    protected function cleanupNotifications(): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('notification'), 't')
            ->where('DATE(t.created_at) < :date')
            ->setParameter('date', $this->date)
            ->executeQuery();
    }

    /**
     * Cleanup attachments
     *
     * @todo will be developed soon
     */
    protected function cleanupAttachments(): void
    {
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
