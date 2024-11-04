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

namespace Atro\Services;

use Atro\Core\ORM\Repositories\RDB;
use Atro\DTO\QueueItemDTO;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;

class MassActionCreator extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
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

        $jobIds = [];

        $select = ['id'];
        $orderBy = 'id';
        if (!empty($this->getMetadata()->get(['entityDefs', $entityName, 'fields', 'createdAt']))) {
            $orderBy = 'createdAt';
            $select[] = $orderBy;
        }

        if (empty($ids)) {
            $sp = $this->getContainer()->get('selectManagerFactory')->create($entityName)
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
                'creatorId'   => $this->qmItem->get('id'),
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

            $qmDto = new QueueItemDTO($name, 'Mass' . ucfirst($action), $jobData);
            $qmDto->setPriority('Crucial');
            $qmDto->setStartFrom(new \DateTime('2299-01-01'));

            $jobIds[] = $this->getContainer()->get('queueManager')->createQueueItem($qmDto);

            $part++;
        }

        if (!empty($jobIds)) {
            foreach ($this->getEntityManager()->getRepository('QueueItem')->where(['id' => $jobIds])->find() as $job) {
                $job->set('startFrom', null);
                $this->getEntityManager()->saveEntity($job);
            }

            QueueManagerBase::updatePublicData('mass' . ucfirst($action), $entityName, [
                "jobIds" => $jobIds,
                "total"  => $total
            ]);
        }

        return true;
    }

    public function getNotificationMessage(Entity $queueItem): string
    {
        return '';
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
