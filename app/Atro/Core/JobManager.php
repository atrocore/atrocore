<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core;

use Atro\Jobs\JobInterface;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\System;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class JobManager
{
    private Container $container;

    public const QUEUE_FILE = 'data/job-queue.log';

    const PAUSE_FILE = 'data/job-manager-pause.txt';

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function executeJob(Entity $job): bool
    {
        $userId = $job->get('ownerUserId');
        if (empty($userId) || $userId == 'system') {
            $auth = new \Espo\Core\Utils\Auth($this->container);
            $auth->useNoAuth();
        } else {
            $user = $this->getEntityManager()->getEntity('User', $userId);
            if (empty($user)) {
                $GLOBALS['log']->error("User $userId not found. Cannot execute job " . $job->get('id'));
                return false;
            }
            $this->getEntityManager()->setUser($user);
            $this->container->setUser($user);
        }

        $job->set('pid', System::getPid());
        $job->set('startedAt', (new \DateTime())->format('Y-m-d H:i:s'));

        $className = $this->getMetadata()->get(['app', 'jobTypes', $job->get('type'), 'handler']);
        if (empty($className) || !is_a($className, JobInterface::class, true)) {
            $job->set('status', 'Failed');
            $job->set('message', "Type class for '{$job->get('type')}' does not exist.");
            $this->getEntityManager()->saveEntity($job);
            return false;
        }

        $job->set('status', 'Running');
        $this->getEntityManager()->saveEntity($job);

        try {
            $this->container->get($className)->run($job);
        } catch (\Throwable $e) {
            $job->set('status', 'Failed');
            $job->set('message', $e->getMessage());
            $this->getEntityManager()->saveEntity($job);
            return false;
        }

        $job->set('status', 'Success');
        $job->set('endedAt', (new \DateTime())->format('Y-m-d H:i:s'));
        $this->getEntityManager()->saveEntity($job);

        return true;
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }
}
