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

class FindMatchesForMatching extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $matchingData = $job->getPayload()['matching'] ?? [];
        if (empty($matchingData)) {
            return;
        }

        if (empty($this->getMetadata()->get("app.matchings.{$matchingData['id']}.isActive"))) {
            return;
        }

        $fieldName = \Atro\Repositories\Matching::prepareFieldName($matchingData['code']);

        $count = $this->getEntityManager()->getRepository($matchingData['entity'])
            ->where([$fieldName => null])
            ->count();

        if ($count === 0) {
            return;
        }

        $entityName = $matchingData['entity'];
        $limit      = $count > 20000 ? $this->getConfig()->get('findMatchesMaxChunkSize', 2000) : 100;
        $allChunks  = (int)ceil($count / $limit);

        // Phase 1: create all jobs as Awaiting so no FindMatchesForRecords job starts
        // running while we are still paginating — that would shift the offset and cause
        // duplicates or missed records.
        $jobs        = [];
        $offset      = 0;
        $chunkNumber = 1;

        while (true) {
            if (empty($this->getMetadata()->get("app.matchings.{$matchingData['id']}.isActive"))) {
                break;
            }

            $collection = $this->getEntityManager()->getRepository($entityName)
                ->where([$fieldName => null])
                ->limit($offset, $limit)
                ->find();

            if (empty($collection[0])) {
                break;
            }

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'        => "Find matches for {$entityName} ($chunkNumber/$allChunks)",
                'type'        => 'FindMatchesForRecords',
                'status'      => 'Awaiting',
                'priority' => 20,
                'payload'     => [
                    'matching'    => $matchingData,
                    'entityName'  => $entityName,
                    'entitiesIds' => array_column($collection->toArray(), 'id'),
                ],
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);

            $jobs[] = $jobEntity;
            $offset += $limit;
            $chunkNumber++;
        }

        // Phase 2: flip all to Pending so the daemon can pick them up
        foreach ($jobs as $jobEntity) {
            $jobEntity->set('status', 'Pending');
            $jobEntity->set('executeTime', (new \DateTime())->format('Y-m-d H:i:s'));
            $this->getEntityManager()->saveEntity($jobEntity);
        }
    }
}
