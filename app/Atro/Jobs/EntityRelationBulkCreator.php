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
        $action       = $data['action'] ?? null;
        $where        = $data['where'] ?? [];
        $foreignWhere = $data['foreignWhere'] ?? [];
        $relationData = $data['relationData'] ?? null;

        if (empty($entityType) || empty($link) || empty($action)) {
            return;
        }

        $foreignEntityType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'addRelationCustomDefs', 'entity'])
            ?? $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);

        if (empty($foreignEntityType)) {
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

        $foreignSp         = $this->getForeignSelectParams($foreignEntityType, $foreignWhere);
        $foreignRepository = $this->getEntityManager()->getRepository($foreignEntityType);
        $foreignTotal      = $foreignRepository->count(array_merge($foreignSp, ['select' => ['id']]));
        if ($foreignTotal === 0) {
            return;
        }

        $maxChunkSize      = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);
        $minChunkSize      = $this->getConfig()->get('massUpdateMinChunkSize', 400);
        $maxConcurrentJobs = $this->getConfig()->get('maxConcurrentJobs', 6);

        $foreignChunkSize = min($foreignTotal, $maxChunkSize);
        $mainChunkSize    = min(
            Record::getChunkSize($total, $maxChunkSize, $minChunkSize, $maxConcurrentJobs),
            max(1, (int)floor($maxChunkSize / $foreignChunkSize))
        );

        $totalMainChunks    = (int)ceil($total / $mainChunkSize);
        $totalForeignChunks = (int)ceil($foreignTotal / $foreignChunkSize);
        $totalChunks        = $totalMainChunks * $totalForeignChunks;

        $childJobType = $action === 'remove' ? 'EntityRelationBulkRemoveAction' : 'EntityRelationBulkAddAction';
        $actionLabel  = $action === 'remove' ? 'Remove relation' : 'Add relation';

        $mainOffset = 0;
        $part       = 0;
        $jobs       = [];

        while (true) {
            $ids = array_column(
                $mainRepository->find(array_merge($mainSp, ['offset' => $mainOffset, 'limit' => $mainChunkSize, 'orderBy' => 'id', 'order' => 'ASC', 'select' => ['id']]))->toArray(),
                'id'
            );
            if (empty($ids)) {
                break;
            }
            $mainOffset += $mainChunkSize;

            $foreignOffset = 0;
            while (true) {
                $foreignIds = array_column(
                    $foreignRepository->find(array_merge($foreignSp, ['offset' => $foreignOffset, 'limit' => $foreignChunkSize, 'orderBy' => 'id', 'order' => 'ASC', 'select' => ['id']]))->toArray(),
                    'id'
                );
                if (empty($foreignIds)) {
                    break;
                }
                $foreignOffset += $foreignChunkSize;
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
        }

        foreach ($jobs as $j) {
            $this->getEntityManager()->saveEntity($j);
        }
    }

    private function getForeignSelectParams(string $entityType, array $where): array
    {
        $sp         = $this->getContainer()->get('selectManagerFactory')->create($entityType)->getSelectParams(['where' => $where]);
        $repository = $this->getEntityManager()->getRepository($entityType);
        $repository->handleSelectParams($sp);

        return $sp;
    }
}
