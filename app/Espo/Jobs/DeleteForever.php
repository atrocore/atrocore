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

use Atro\Core\Container;
use Espo\Core\EventManager\Event;
use Espo\Core\Jobs\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityManager;

class DeleteForever extends Base
{
    /**
     * @var string
     */
    private $date;

    /**
     * @var string
     */
    private $db;

    /**
     * @inheritDoc
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->db = $this->getConfig()->get('database')['dbname'];
    }

    /**
     * Run cron job
     *
     * @return bool
     */
    public function run($data = null, $targetId = null, $targetType = null, $scheduledJobId = null): bool
    {
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $scheduledJobId ?? 'DeleteForever');

        if(empty($scheduledJob) ){
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

        // dispatch an event
        $this->getContainer()->get('eventManager')->dispatch('DeleteForeverJob', 'run', new Event());

        return true;
    }

    protected function cleanupEntityTeam()
    {
        foreach ($this->getMetadata()->get('entityDefs', []) as $scope => $data) {
            $table = Util::toUnderScore($scope);
            try {
                $this->getEntityManager()->nativeQuery("DELETE FROM entity_team WHERE entity_type='$scope' AND entity_id NOT IN (SELECT id FROM $table WHERE deleted=0)");
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * Cleanup jobs
     */
    protected function cleanupJobs(): void
    {
        $this->exec("DELETE FROM job WHERE DATE(execute_time)<'{$this->date}' AND status IN ('Success','Failed')");
    }

    /**
     * Cleanup scheduled job logs
     */
    protected function cleanupScheduledJobLog(): void
    {
        $this->exec("DELETE FROM scheduled_job_log_record WHERE DATE(execution_time)<'{$this->date}'");
    }

    /**
     * Cleanup deleted
     */
    protected function cleanupDeleted(): void
    {
        $tables = $this->getEntityManager()->nativeQuery('show tables')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {

            $columns = $this->getEntityManager()->nativeQuery("SHOW COLUMNS FROM {$this->db}.$table")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('deleted', $columns)) {
                continue 1;
            }
            if ($table == 'attachment') {
                $fileManager = $this->getContainer()->get('fileStorageManager');
                $repository = $this->getEntityManager()->getRepository('Attachment');
                $attachments = $repository
                    ->where([
                        'deleted' => 1,
                        'createdAt<=' => $this->date
                    ])
                    ->find(["withDeleted" => true]);
                foreach ($attachments as $entity){
                    // unlink file
                    $fileManager->unlink($entity);
                    // remove record from DB table
                    $repository->deleteFromDb($entity->get('id'));
                }
                continue 1;
            }
            if(in_array('modified_at',$columns)){
                $this->exec("DELETE FROM {$this->db}.$table WHERE deleted=1 AND DATE(modified_at)<'{$this->date}'");
            }

            if (!in_array('modified_at', $columns) && in_array('created_at', $columns) ) {
                $this->exec("DELETE FROM {$this->db}.$table WHERE deleted=1 DATE(created_at)<'{$this->date}' ");
            }

            if (!in_array('modified_at', $columns) && !in_array('created_at', $columns) ) {
                $this->exec("DELETE FROM {$this->db}.$table WHERE deleted=1");
            }

        }
    }

    /**
     * Cleanup auth log
     */
    protected function cleanupAuthLog(): void
    {
        $this->exec("DELETE FROM `auth_log_record` WHERE DATE(created_at)<'{$this->date}'");
    }

    /**
     * Cleanup action history
     */
    protected function cleanupActionHistory(): void
    {
        $this->exec("DELETE FROM `action_history_record` WHERE DATE(created_at)<'{$this->date}'");
    }

    /**
     * Cleanup notifications
     */
    protected function cleanupNotifications(): void
    {
        $this->exec("DELETE FROM `notification` WHERE DATE(created_at)<'{$this->date}'");
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
            $this->getEntityManager()->nativeQuery($sql);
        } catch (\PDOException $e) {
            $GLOBALS['log']->error('DeleteForever: ' . $e->getMessage() . ' | ' . $sql);
        }
    }


}
