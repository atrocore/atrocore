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

declare(strict_types=1);

namespace Atro\Jobs;

use Atro\Core\ORM\Repositories\RDB;
use Atro\Entities\Job;

class MassActionCreator extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        $entityName = $data['entityName'];
        $action = $data['action'];
        $ids = $data['ids'] ?? [];
        $total = (int)$data['total'];
        $chunkSize = $data['chunkSize'];
        $totalChunks = (int)ceil($total / $chunkSize);
        $actionEntity = null;

        $additionJobData = $data['params']['additionalJobData'] ?? [];
        if ($action === 'action' && !empty($additionJobData['actionId'])) {
            $actionEntity = $this->getEntityManager()->getEntity('Action', $additionJobData['actionId']);
            if (empty($actionEntity)) {
                return;
            }
        }

        /** @var RDB $repository */
        $repository = $this->getEntityManager()->getRepository($entityName);

        $offset = 0;
        $part = 0;

        $jobs = [];

        $select = ['id'];
        $orderBy = 'id';

        if (empty($ids)) {
            $sp = $this->getServiceFactory()
                ->create($entityName)
                ->getSelectParams(['where' => $data['params']['where']], true, true);
            $sp['select'] = $select;
        }

        if (!empty($actionEntity)) {
            $name = $actionEntity->get('name');
        } else if ($action === 'custom') {
            $name = $this->translate($data['type'], 'massActions');
        } else {
            $name = $this->translate($action, 'massActions');
        }

        while (true) {
            if (!empty($ids)) {
                $collection = $repository
                    ->select($select)
                    ->where(['id' => $ids])
                    ->limit($offset, $chunkSize)
                    ->order($orderBy)
                    ->find();

            } else {
                if (!empty($data['selectParams'])) {
                    $sp = $data['selectParams'];
                    $sp['select'] = $select;
                }

                $collection = $repository
                    ->limit($offset, $chunkSize)
                    ->order($orderBy)
                    ->find($sp);
            }

            $offset = $offset + $chunkSize;

            $collectionIds = array_column($collection->toArray(), 'id');
            if (empty($collectionIds)) {
                break;
            }

            $jobData = array_merge($additionJobData, [
                'creatorId'   => $job->get('id'),
                'entityType'  => $entityName,
                'total'       => $total,
                'chunkSize'   => count($collectionIds),
                'totalChunks' => $totalChunks,
                'ids'         => $collectionIds,
                'part'        => $part + 1,
            ]);

            if ($action === 'delete' && !empty($data['params']['permanently'])) {
                $jobData['deletePermanently'] = true;
            }

            if ($action === 'custom') {
                $type = $data['type'];
            } else {
                $type = $action === 'action' ? 'ActionHandler' : 'Mass' . ucfirst($action);
            }

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'        => $name . ': ' . $entityName . ($part > 0 ? " ($part)" : ""),
                'type'        => $type,
                'status'      => 'Awaiting',
                'priority'    => $job->get('priority'),
                'ownerUserId' => $job->get('ownerUserId'),
                'payload'     => $jobData
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);

            $jobs[] = $jobEntity;

            $part++;
        }

        if (!empty($jobs)) {
            $jobsCount = count($jobs);

            $i = 1;
            foreach ($jobs as $job) {
                $job->set('name', sprintf($this->translate('massActionJobName'), $name, $entityName, $i, $jobsCount));
                $job->set('status', 'Pending');
                $job->set('executeTime', (new \DateTime())->format('Y-m-d H:i:s'));
                $this->getEntityManager()->saveEntity($job);
                $i++;
            }
        }
    }
}
