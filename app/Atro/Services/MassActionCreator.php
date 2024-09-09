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

        /** @var RDB $repository */
        $repository = $this->getEntityManager()->getRepository($entityName);

        $offset = 0;
        $part = 0;

        $jobIds = [];

        while (true) {
            if (!empty($ids)) {
                $collection = $repository
                    ->select(['id'])
                    ->where(['id' => $ids])
                    ->limit($offset, $chunkSize)
                    ->order('id', 'ASC')
                    ->find();
            } else {
                $collection = $repository
                    ->limit($offset, $chunkSize)
                    ->order('id', 'ASC')
                    ->find(array_merge($data['selectParams'], ['select' => ['id']]));
            }

            $collectionIds = array_column($collection->toArray(), 'id');
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

            $offset = $offset + $chunkSize;
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
}
