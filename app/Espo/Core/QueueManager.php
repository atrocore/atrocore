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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Espo\Core;

use Espo\Core\Exceptions\Duplicate;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\System;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\Orm\EntityManager;
use Espo\Repositories\QueueItem as Repository;
use Espo\Services\QueueManagerServiceInterface;

/**
 * Class QueueManager
 */
class QueueManager extends Injectable
{
    const FILE_PATH = 'data/queue-exist.log';

    public function __construct()
    {
        $this->addDependency('container');
    }

    /**
     * @param int $stream
     *
     * @return bool
     * @throws Error
     */
    public function run(int $stream): bool
    {
        $result = $this->runJob($stream);

        if ($this->getRepository()->isQueueEmpty() && file_exists(self::FILE_PATH)) {
            unlink(self::FILE_PATH);
        }

        return $result;
    }

    public function push(string $name, string $serviceName, array $data = [], string $priority = 'Normal', string $md5Hash = ''): bool
    {
        // validation
        if (!$this->isService($serviceName)) {
            return false;
        }

        return $this->createQueueItem($name, $serviceName, $data, $priority, $md5Hash);
    }

    public function tryAgain(string $id): bool
    {
        $item = $this->getEntityManager()->getRepository('QueueItem')->get($id);
        if (empty($item)) {
            return false;
        }

        $item->set('status', 'Pending');
        $item->set('pid', null);
        $item->set('message', null);
        $item->set('stream', null);
        $this->getEntityManager()->saveEntity($item);

        file_put_contents(self::FILE_PATH, '1');

        return true;
    }

    protected function createQueueItem(string $name, string $serviceName, array $data, string $priority, string $md5Hash): bool
    {
        /** @var Repository $repository */
        $repository = $this->getEntityManager()->getRepository('QueueItem');

        // delete old
        $repository->deleteOldRecords();

        /** @var User $user */
        $user = $this->getContainer()->get('user');

        $item = $repository->get();
        $item->set(
            [
                'name'           => $name,
                'serviceName'    => $serviceName,
                'priority'       => $priority,
                'data'           => $data,
                'createdById'    => $user->get('id'),
                'ownerUserId'    => $user->get('id'),
                'assignedUserId' => $user->get('id'),
                'createdAt'      => date("Y-m-d H:i:s")
            ]
        );

        if (!empty($md5Hash)) {
            $item->set('md5Hash', $md5Hash);
            $duplicate = $repository->select(['id'])->where(['md5Hash' => $md5Hash, 'status' => ['Pending', 'Running']])->findOne();
            if (!empty($duplicate)) {
                throw new Duplicate($this->getContainer()->get('language')->translate('jobExist', 'exceptions', 'QueueItem'));
            }
        }

        $this->getEntityManager()->saveEntity($item, ['skipAll' => true]);

        foreach ($user->get('teams')->toArray() as $row) {
            $repository->relate($item, 'teams', $row['id']);
        }

        file_put_contents(self::FILE_PATH, '1');

        return true;
    }

    /**
     * @param string $serviceName
     *
     * @return bool
     * @throws Error
     */
    protected function isService(string $serviceName): bool
    {
        if (!$this->getServiceFactory()->checkExists($serviceName)) {
            throw new Error("No such service '$serviceName'");
        }

        if (!$this->getServiceFactory()->create($serviceName) instanceof QueueManagerServiceInterface) {
            throw new Error("Service '$serviceName' should be instance of QueueManagerServiceInterface");
        }

        return true;
    }

    /**
     * @param int $stream
     *
     * @return bool
     * @throws Error
     */
    protected function runJob(int $stream): bool
    {
        if ($this->getRepository()->isQueueEmpty()) {
            return false;
        }

        if (!empty($item = $this->getRepository()->getRunningItemForStream($stream))) {
            $this->setStatus($item, 'Failed');
            $GLOBALS['log']->error("QM failed: The item '{$item->get('id')}' was not completed in the previous run.");
            return false;
        }

        $item = $this->getRepository()->getPendingItemForStream($stream);

        if (empty($item)) {
            return false;
        }

        // auth
        if ($item->get('createdById') === 'system') {
            $user = $this->getEntityManager()->getRepository('User')->get('system');
            $user->set('isAdmin', true);
            $user->set('ipAddress', $_SERVER['REMOTE_ADDR']);
        } else {
            $user = $item->get('createdBy');
        }
        $this->getContainer()->setUser($user);
        $this->getEntityManager()->setUser($user);

        if (!empty($user->get('portalId'))) {
            $this->getContainer()->setPortal($user->get('portal'));
        }

        // reload language
        $this->getContainer()->reload('language');

        // running
        $item->set('stream', $stream);
        $this->setStatus($item, 'Running');

        // service validation
        if (!$this->isService((string)$item->get('serviceName'))) {
            $this->setStatus($item, 'Failed');
            $GLOBALS['log']->error("QM failed: No such QM service '" . $item->get('serviceName') . "'");

            return false;
        }

        // prepare data
        $data = [];
        if (!empty($item->get('data'))) {
            $data = json_decode(json_encode($item->get('data')), true);
        }

        try {
            $this->getServiceFactory()->create($item->get('serviceName'))->run($data);
        } catch (\Throwable $e) {
            $this->setStatus($item, 'Failed', $e->getMessage());
            $GLOBALS['log']->error('QM failed: ' . $e->getMessage() . ' ' . $e->getTraceAsString());

            return false;
        }

        $this->setStatus($item, 'Success');

        return true;
    }

    protected function setStatus(Entity $item, string $status, string $message = null): void
    {
        $item->set('status', $status);
        if ($status === 'Running') {
            $item->set('pid', System::getPid());
        }

        if ($message !== null) {
            $item->set('message', $message);
        }
        $this->getEntityManager()->saveEntity($item);
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    /**
     * @return ServiceFactory
     */
    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getContainer()->get('serviceFactory');
    }

    /**
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    protected function getRepository(): \Espo\Repositories\QueueItem
    {
        return $this->getEntityManager()->getRepository('QueueItem');
    }
}
