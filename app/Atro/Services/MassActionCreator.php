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
use Doctrine\DBAL\Connection;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;

class MassActionCreator extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        $entityName = $data['entityName'];
        $action = $data['action'];
        $total = (int)$data['total'];
        $chunkSize = $data['chunkSize'];
        $totalChunks = (int)ceil($total / $chunkSize);

        $sp = array_merge($data['selectParams'], ['select' => ['id']]);

        /** @var RDB $repository */
        $repository = $this->getEntityManager()->getRepository($entityName);

        $offset = 0;
        $part = 0;

        $jobIds = [];

        $idsToSkip = [];

        $createdAt = null;

        $hasCreatedAt = !empty($this->getMetadata()->get(['entityDefs', $entityName, 'fields', 'createdAt']));

        while (true) {
            $collectionIds = [];

            if (!empty($ids)) {
                $collection = $repository
                    ->select(['id'])
                    ->where(['id' => $ids])
                    ->limit($offset, $chunkSize)
                    ->find();
                $collectionIds = array_column($collection->toArray(), 'id');
                $offset = $offset + $chunkSize;
            } else {
                $qb = $repository->getMapper()->createSelectQueryBuilder($repository->get(), $sp, true);
                if ($hasCreatedAt) {
                    $qb->select('id, created_at');
                } else {
                    $qb->select('id');
                }

                if (!empty($idsToSkip)) {
                    $qb->andWhere('id NOT IN (:idsToSkip)')
                        ->setParameter('idsToSkip', $idsToSkip, Connection::PARAM_STR_ARRAY);
                }

                if ($hasCreatedAt && !empty($createdAt)) {
                    $qb->andWhere('created_at >= :createdAt')
                        ->setParameter('createdAt', $createdAt);
                }

                $qb->setFirstResult(0);
                $qb->setMaxResults($chunkSize);
                if ($hasCreatedAt) {
                    $qb->orderBy('created_at,id');
                } else {
                    $qb->orderBy('id');
                }

                foreach ($qb->fetchAllAssociative() as $row) {
                    if ($hasCreatedAt) {
                        $createdAt = $row['created_at'];
                    }
                    $collectionIds[] = $row['id'];
                    $idsToSkip[] = $row['id'];
                    if (count($idsToSkip) > 50000) {
                        array_shift($idsToSkip);
                    }
                }

                $idsToSkip = array_values($idsToSkip);
            }

            if (empty($collectionIds)) {
                break;
            }

            $jobData = array_merge($data['additionJobData'], [
                'entityType'  => $entityName,
                'total'       => $total,
                'chunkSize'   => count($collectionIds),
                'totalChunks' => $totalChunks,
                'ids'         => $collectionIds,
            ]);

            if ($action === 'delete' && !empty($params['permanently'])) {
                $jobData['deletePermanently'] = true;
            }

            $name = $this->translate($action, 'massActions') . ': ' . $entityName;
            if ($part > 0) {
                $name .= " ($part)";
            }

            $jobIds[] = $this->getContainer()->get('queueManager')
                ->createQueueItem($name, 'Mass' . ucfirst($action), $jobData);

            $part++;

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
