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

        $additionJobData = $data['params']['additionalJobData'] ?? [];

        /** @var RDB $repository */
        $repository = $this->getEntityManager()->getRepository($entityName);

        $offset = 0;
        $part = 0;

        $jobs = [];

        $select = ['id'];
        $orderBy = 'id';
        if (!empty($this->getMetadata()->get(['entityDefs', $entityName, 'fields', 'createdAt']))) {
            $orderBy = 'createdAt';
            $select[] = $orderBy;
        }

        if (empty($ids)) {
            $sp = $this->getServiceFactory()
                ->create($entityName)
                ->getSelectParams(['where' => $data['params']['where']], true, true);
            $sp['select'] = $select;
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
            ]);

            if ($action === 'delete' && !empty($data['params']['permanently'])) {
                $jobData['deletePermanently'] = true;
            }

            $name = $this->translate($action, 'massActions') . ': ' . $entityName;
            if ($part > 0) {
                $name .= " ($part)";
            }

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'        => $name,
                'type'        => 'Mass' . ucfirst($action),
                'status'      => 'Awaiting',
                'priority'    => 150,
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
                $job->set('name', sprintf($this->translate('massActionJobName'), $this->translate($action, 'massActions'), $entityName, $i, $jobsCount));
                $job->set('status', 'Pending');
                $job->set('executeTime', (new \DateTime())->format('Y-m-d H:i:s'));
                $this->getEntityManager()->saveEntity($job);
                $i++;
            }
        }
    }
}
