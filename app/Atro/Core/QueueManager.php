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

namespace Atro\Core;

use Espo\Core\Exceptions\Duplicate;
use Espo\Core\Exceptions\Error;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\System;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\Orm\EntityManager;
use Espo\Repositories\QueueItem as Repository;
use Espo\Services\QueueManagerServiceInterface;

class QueueManager
{
    const QUEUE_DIR_PATH = 'data/queue';
    const FILE_PATH = 'data/queue-exist.log';

    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function run(int $stream): bool
    {
        return $this->runJob($stream);
    }

    public function push(string $name, string $serviceName, array $data = [], string $priority = 'Normal', string $md5Hash = ''): bool
    {
        // validation
        if (!$this->isService($serviceName)) {
            return false;
        }

        $id = $this->createQueueItem($name, $serviceName, $data, $priority, $md5Hash);

        return !empty($id);
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

        return true;
    }

    public function createQueueItem(string $name, string $serviceName, array $data = [], string $priority = 'Normal', string $md5Hash = ''): string
    {
        /** @var Repository $repository */
        $repository = $this->getEntityManager()->getRepository('QueueItem');

        // delete old
        $repository->deleteOldRecords();

        /** @var User $user */
        $user = $this->container->get('user');

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
                throw new Duplicate($this->container->get('language')->translate('jobExist', 'exceptions', 'QueueItem'));
            }
        }

        $this->getEntityManager()->saveEntity($item);

        foreach ($user->get('teams')->toArray() as $row) {
            $repository->relate($item, 'teams', $row['id']);
        }

        return $item->get('id');
    }

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

    protected function getItemId(): ?string
    {
        $queueDir = self::QUEUE_DIR_PATH;
        if (!file_exists($queueDir)) {
            return null;
        }

        $dirs = scandir($queueDir);

        // exit if there are no dirs in queue dir
        if (!array_key_exists(2, $dirs)) {
            return null;
        }

        foreach ($dirs as $dirName) {
            if (in_array($dirName, ['.', '..'])) {
                continue;
            }

            if (!is_dir("$queueDir/$dirName")) {
                unlink("$queueDir/$dirName");
                continue;
            }

            $files = scandir("$queueDir/$dirName");

            // exit if there are no files in dir
            if (!array_key_exists(2, $files)) {
                continue;
            }

            foreach ($files as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                $itemId = file_get_contents("$queueDir/$dirName/$file");
                unlink("$queueDir/$dirName/$file");

                return $itemId;
            }
        }

        return null;
    }

    protected function runJob(int $stream): bool
    {
        $itemId = $this->getItemId();
        if (empty($itemId)) {
            return false;
        }

        /**
         * Trying to find needed job in 10 sec, because DB could create job too long
         */
        $count = 0;
        while (empty($item = $this->getRepository()->get($itemId))) {
            $count++;
            if ($count === 10) {
                $GLOBALS['log']->error("QM failed: No such QM item '$itemId' in DB.");
                return false;
            }
            sleep(1);
        }

        // auth
        if ($item->get('createdById') === 'system') {
            $user = $this->getEntityManager()->getRepository('User')->get('system');
            $user->set('isAdmin', true);
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $user->set('ipAddress', $_SERVER['REMOTE_ADDR']);
            }
        } else {
            $user = $item->get('createdBy');
        }
        $this->container->setUser($user);
        $this->getEntityManager()->setUser($user);

        if (!empty($user->get('portalId'))) {
            $this->container->setPortal($user->get('portal'));
        }

        // reload language
        $this->container->reload('language');

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
            $service = $this->getServiceFactory()->create($item->get('serviceName'));
            $service->setQueueItem($item);
            $service->run($data);
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

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    protected function getRepository(): \Espo\Repositories\QueueItem
    {
        return $this->getEntityManager()->getRepository('QueueItem');
    }
}
