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

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function executeJob(Entity $job): bool
    {
        // auth as system
        $auth = new \Espo\Core\Utils\Auth($this->container);
        $auth->useNoAuth();

        $job->set('pid', System::getPid());
        $job->set('startedAt', (new \DateTime())->format('Y-m-d H:i:s'));

        $className = $this->getMetadata()->get(['app', 'jobHandler', $job->get('handler')]);
        if (empty($className) || !is_a($className, JobInterface::class, true)) {
            $job->set('status', 'Failed');
            $job->set('message', "Handler class for '{$job->get('handler')}' does not exist.");
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
