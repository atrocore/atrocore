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

namespace Atro\Repositories;

use Atro\ActionTypes\Set;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\JobManager;
use Atro\Core\Templates\Repositories\Base;
use Atro\Entities\Job as JobEntity;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class Job extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (empty($entity->get('executeTime'))) {
            $entity->set('executeTime', date('Y-m-d H:i:s'));
        }

        if (empty($entity->get('ownerUserId'))) {
            $entity->set('ownerUserId', $this->getEntityManager()->getUser()->get('id'));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('status')) {
            if ($entity->get('status') === 'Pending') {
                $entity->set('pid', null);
                $entity->set('startedAt', null);
                $entity->set('endedAt', null);
                $entity->set('message', null);
            }

            if (!$entity->isNew() && !empty($entity->getFetched('status'))) {
                $transitions = [
                    "Running"  => [
                        "Pending"
                    ],
                    "Success"  => [
                        "Running"
                    ],
                    "Canceled" => [
                        "Pending",
                        "Running"
                    ],
                    "Awaiting" => [],
                    "Failed"   => [
                        "Pending",
                        "Running"
                    ],
                    "Pending"  => [
                        "Success",
                        "Canceled",
                        "Failed"
                    ]
                ];

                if (!isset($transitions[$entity->get('status')])) {
                    throw new Error("Unknown status '{$entity->get('status')}'.");
                }

                if (!in_array($entity->getFetched('status'), $transitions[$entity->get('status')])) {
                    throw new Error("It is impossible to change the status from '{$entity->getFetched('status')}' to '{$entity->get('status')}'.");
                }
            }
        }
    }

    /**
     * @param JobEntity $entity
     * @param array     $options
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->get('status') === 'Pending' && !empty($entity->get('type'))) {
            file_put_contents(JobManager::QUEUE_FILE, '1');
        }

        if ($entity->isAttributeChanged('status')) {
            $this->checkActionExecution($entity);

            if ($entity->get('status') === 'Canceled' && !empty($entity->get('pid'))) {
                exec("kill -9 {$entity->get('pid')}");
            }

            if (in_array($entity->get('status'), ['Success', 'Failed'])) {
                $className = $this->getMetadata()->get(['action', 'types', 'set']);
                if (empty($className)) {
                    return;
                }

                /** @var Set $actionTypeService */
                $actionTypeService = $this->getInjection('container')->get($className);
                $actionTypeService->checkJob($entity);
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('status') == 'Running') {
            throw new BadRequest($this->getLanguage()->translate('jobIsRunning', 'exceptions', 'Job'));
        }

        parent::beforeRemove($entity, $options);
    }

    public function checkActionExecution(JobEntity $job): void
    {
        $actionExecutionId = $job->getPayload()['actionExecutionId'] ?? null;
        if (empty($actionExecutionId)) {
            return;
        }

        $res = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->getConnection()->quoteIdentifier('job'))
            ->where('payload LIKE :payload')
            ->andWhere('status IN (:statuses)')
            ->andWhere('deleted = :false')
            ->setParameter('payload', '%"actionExecutionId":"' . $actionExecutionId . '"%')
            ->setParameter('statuses', ['Pending', 'Running'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if (!empty($res)) {
            return;
        }

        $execution = $this->getEntityManager()->getRepository('ActionExecution')->get($actionExecutionId);
        if (!empty($execution)) {
            $execution->set('status', 'done');
            $this->getEntityManager()->saveEntity($execution);
        }
    }

    public function cancelMatchingJobs(string $matchingId): void
    {
        $jobs = $this->getEntityManager()->getRepository('Job')
            ->where([
                'type'   => 'FindMatchingMatches',
                'status' => ['Pending', 'Running']
            ])
            ->find();

        foreach ($jobs as $job) {
            $jobMatchingId = $job->getPayload()['matching']['id'] ?? null;
            if ($jobMatchingId === $matchingId) {
                $job->set('status', 'Canceled');
                $this->getEntityManager()->saveEntity($job);
            }
        }

        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('job'))
            ->set('status', ':canceled')
            ->where('payload LIKE :payload')
            ->andWhere('status = :pending')
            ->andWhere('deleted = :false')
            ->andWhere('type in (:types)')
            ->setParameter('payload', '%"id":"' . $matchingId . '"%')
            ->setParameter('canceled', 'Canceled')
            ->setParameter('pending', 'Pending')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('types', ['FindMatchesForRecord', 'StopFindingMatches'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ->executeQuery();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
