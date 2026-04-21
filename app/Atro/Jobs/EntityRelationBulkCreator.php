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

use Atro\Entities\Job;
use Atro\Services\Record;

class EntityRelationBulkCreator extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        $entityType   = $data['entityType'] ?? '';
        $link         = $data['link'] ?? '';
        $action       = $data['action'] ?? 'add';
        $where        = $data['where'] ?? [];
        $foreignWhere = $data['foreignWhere'] ?? [];
        $relationData = $data['relationData'] ?? null;

        if (empty($entityType) || empty($link)) {
            return;
        }

        $foreignEntityType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'addRelationCustomDefs', 'entity'])
            ?? $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);

        if (empty($foreignEntityType)) {
            return;
        }

        $foreignIds = $this->resolveIds($foreignEntityType, $foreignWhere);
        if (empty($foreignIds)) {
            return;
        }

        $mainService    = $this->getServiceFactory()->create($entityType);
        $mainSp         = $mainService->getSelectParams(['where' => $where], true, true);
        $mainRepository = $this->getEntityManager()->getRepository($entityType);
        $mainRepository->handleSelectParams($mainSp);

        $total = $mainRepository->count(array_merge($mainSp, ['select' => ['id']]));
        if ($total === 0) {
            return;
        }

        $maxChunkSize      = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);
        $minChunkSize      = $this->getConfig()->get('massUpdateMinChunkSize', 400);
        $maxConcurrentJobs = $this->getConfig()->get('maxConcurrentJobs', 6);
        $chunkSize         = Record::getChunkSize($total, $maxChunkSize, $minChunkSize, $maxConcurrentJobs);
        $totalChunks       = (int)ceil($total / $chunkSize);

        $childJobType = 'EntityRelationBulkAction';
        $actionLabel  = $action === 'remove' ? 'Remove relation' : 'Add relation';

        $offset = 0;
        $part   = 0;
        $jobs   = [];

        while (true) {
            $collection = $mainRepository
                ->limit($offset, $chunkSize)
                ->order('id')
                ->find($mainSp);

            $ids = array_column($collection->toArray(), 'id');
            if (empty($ids)) {
                break;
            }

            $offset += $chunkSize;
            $part++;

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'        => sprintf('%s: %s.%s (%d/%d)', $actionLabel, $entityType, $link, $part, $totalChunks),
                'type'        => $childJobType,
                'priority'    => $job->get('priority'),
                'ownerUserId' => $job->get('ownerUserId'),
                'payload'     => [
                    'creatorId'    => $job->get('id'),
                    'action'       => $action,
                    'entityType'   => $entityType,
                    'link'         => $link,
                    'ids'          => $ids,
                    'foreignIds'   => $foreignIds,
                    'relationData' => $relationData,
                    'total'        => $total,
                    'totalChunks'  => $totalChunks,
                    'part'         => $part,
                ],
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);
            $jobs[] = $jobEntity;
        }

        foreach ($jobs as $j) {
            $this->getEntityManager()->saveEntity($j);
        }
    }

    private function resolveIds(string $entityType, array $where): array
    {
        $selectManagerFactory = $this->getContainer()->get('selectManagerFactory');
        $sp                   = $selectManagerFactory->create($entityType)->getSelectParams(['where' => $where]);
        $repository           = $this->getEntityManager()->getRepository($entityType);
        $repository->handleSelectParams($sp);
        $collection = $repository->find(array_merge($sp, ['select' => ['id']]));

        return array_column($collection->toArray(), 'id');
    }
}
