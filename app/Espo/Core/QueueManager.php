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

namespace Espo\Core;

use Espo\Core\Exceptions\Error;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\Orm\EntityManager;
use Espo\Services\QueueManagerServiceInterface;
use Treo\Core\ServiceFactory;

/**
 * Class QueueManager
 */
class QueueManager
{
    /**
     * @var Container
     */
    private $container;

    /**
     * QueueManager constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param int $stream
     *
     * @return bool
     * @throws Error
     */
    public function run(int $stream): bool
    {
        return $this->runJob($stream);
    }

    /**
     * @param string   $name
     * @param string   $serviceName
     * @param array    $data
     * @param bool|int $isWriting
     *
     * @return bool
     * @throws Error
     */
    public function push(string $name, string $serviceName, array $data = [], $isWriting = false): bool
    {
        // validation
        if (!$this->isService($serviceName)) {
            return false;
        }

        // @todo $isWriting should be bool only !
        if (is_int($isWriting)) {
            $isWriting = $isWriting === 1;
        }

        return $this->createQueueItem($name, $serviceName, $data, $isWriting);
    }

    /**
     * @param string $name
     * @param string $serviceName
     * @param array  $data
     * @param bool   $isWriting
     *
     * @return bool
     * @throws Error
     */
    protected function createQueueItem(string $name, string $serviceName, array $data, bool $isWriting): bool
    {
        /** @var User $user */
        $user = $this->getContainer()->get('user');

        $item = $this->getEntityManager()->getEntity('QueueItem');
        $item->set(
            [
                'name'           => $name,
                'serviceName'    => $serviceName,
                'isWriting'      => $isWriting,
                'data'           => $data,
                'sortOrder'      => $this->getNextSortOrder(),
                'createdById'    => $user->get('id'),
                'ownerUserId'    => $user->get('id'),
                'assignedUserId' => $user->get('id'),
                'createdAt'      => date("Y-m-d H:i:s")
            ]
        );
        $this->getEntityManager()->saveEntity($item, ['skipAll' => true]);

        foreach ($user->get('teams')->toArray() as $row) {
            $this->getEntityManager()->getRepository('QueueItem')->relate($item, 'teams', $row['id']);
        }

        return true;
    }

    /**
     * @return int
     */
    protected function getNextSortOrder(): int
    {
        // prepare result
        $result = 0;

        $data = $this
            ->getEntityManager()
            ->getRepository('QueueItem')
            ->select(['sortOrder'])
            ->find()
            ->toArray();

        if (!empty($data)) {
            $result = (max(array_column($data, 'sortOrder'))) + 1;
        }

        return $result;
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
        if (!empty($item = $this->getRepository()->getRunningItemForStream($stream))) {
            $this->setStatus($item, 'Failed');
            $GLOBALS['log']->error("QM failed: The item was not completed in the previous run.");
            return false;
        }

        $item = $this->getRepository()->getPendingItemForStream();

        if (empty($item) || (!empty($item->get('isWriting')) && $stream !== 1)) {
            return true;
        }

        // auth
        $this->getContainer()->setUser($item->get('createdBy'));
        $this->getEntityManager()->setUser($item->get('createdBy'));

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
            $this->setStatus($item, 'Failed');
            $GLOBALS['log']->error('QM failed: ' . $e->getMessage() . ' ' . $e->getTraceAsString());

            return false;
        }

        $this->setStatus($item, 'Success');

        return true;
    }

    /**
     * @param Entity $item
     * @param string $status
     */
    protected function setStatus(Entity $item, string $status): void
    {
        $item->set('status', $status);
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
        return $this->container;
    }

    protected function getRepository(): \Espo\Repositories\QueueItem
    {
        return $this->getEntityManager()->getRepository('QueueItem');
    }
}
