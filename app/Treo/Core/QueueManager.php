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
 * Website: https://treolabs.com
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

namespace Treo\Core;

use Espo\Core\Exceptions\Error;
use Espo\Orm\EntityManager;
use Treo\Entities\QueueItem;
use Treo\Services\QueueManagerServiceInterface;

/**
 * Class QueueManager
 */
class QueueManager
{
    use \Treo\Traits\ContainerTrait;

    const QUEUE_PATH = 'data/qm-items-%s.json';

    /**
     * @param int $stream
     *
     * @return bool
     * @throws Error
     */
    public function run(int $stream): bool
    {
        // get data
        $data = $this->getFileData($stream);

        if (!isset($data[0])) {
            return false;
        }

        return $this->runJob($stream, (string)$data[0]);
    }

    /**
     * @param string $name
     * @param string $serviceName
     * @param array  $data
     * @param int    $stream
     *
     * @return bool
     * @throws Error
     */
    public function push(string $name, string $serviceName, array $data = [], int $stream = 0): bool
    {
        // validation
        if (!$this->isService($serviceName) || $stream < 0 || $stream > 9) {
            return false;
        }

        return $this->createQueueItem($name, $serviceName, $data, $stream);
    }

    /**
     * Unset item
     *
     * @param int    $stream
     * @param string $id
     */
    public function unsetItem(int $stream, string $id): void
    {
        $data = $this->getFileData($stream);
        foreach ($data as $k => $item) {
            if ($item == $id) {
                unset($data[$k]);
            }
        }

        // prepare path
        $path = sprintf(self::QUEUE_PATH, $stream);

        if (empty($data) && file_exists($path)) {
            unlink($path);
        } else {
            file_put_contents($path, json_encode(array_values($data)));
        }
    }

    /**
     * @param string $name
     * @param string $serviceName
     * @param array  $data
     * @param int    $stream
     *
     * @return bool
     * @throws Error
     */
    protected function createQueueItem(string $name, string $serviceName, array $data, int $stream): bool
    {
        $item = $this->getEntityManager()->getEntity('QueueItem');
        $item->set(
            [
                'name'        => $name,
                'serviceName' => $serviceName,
                'stream'      => $stream,
                'data'        => $data,
                'sortOrder'   => $this->getNextSortOrder(),
                'createdById' => $this->getContainer()->get('user')->get('id'),
                'createdAt'   => date("Y-m-d H:i:s")
            ]
        );
        $this->getEntityManager()->saveEntity($item, ['skipAll' => true]);

        // prepare file data
        $fileData = $this->getFileData($stream);

        // push new item
        $fileData[] = $item->get('id');

        // prepare path
        $path = sprintf(self::QUEUE_PATH, $stream);

        // save
        file_put_contents($path, json_encode($fileData));

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
    protected function isService(string $serviceName)
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
     * @return array
     */
    protected function getFileData(int $stream): array
    {
        $data = [];

        // prepare path
        $path = sprintf(self::QUEUE_PATH, $stream);

        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
        }

        return $data;
    }

    /**
     * @param int    $stream
     * @param string $id
     *
     * @return bool
     * @throws Error
     */
    protected function runJob(int $stream, string $id): bool
    {
        // unset
        $this->unsetItem($stream, $id);

        // get item
        if (empty($item = $this->getItem($id))) {
            return false;
        }

        // auth
        $this->getContainer()->setUser($item->get('createdBy'));
        $this->getEntityManager()->setUser($item->get('createdBy'));

        // running
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
     * @param QueueItem $item
     * @param string    $status
     */
    protected function setStatus(QueueItem $item, string $status): void
    {
        $item->set('status', $status);
        $this->getEntityManager()->saveEntity($item);
    }

    /**
     * @param string $id
     *
     * @return null|QueueItem
     */
    protected function getItem(string $id): ?QueueItem
    {
        return $this->getEntityManager()->getRepository('QueueItem')->get($id);
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
}
