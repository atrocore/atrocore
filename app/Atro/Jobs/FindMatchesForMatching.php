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

        if (empty($this->getConfig()->get("matchings.{$matchingData['id']}"))) {
            return;
        }

        $fieldName = \Atro\Repositories\Matching::prepareFieldName($matchingData['id']);

        $count = $this->getEntityManager()->getRepository($matchingData['entity'])
            ->where([$fieldName => null])
            ->count();

        if ($count === 0) {
            return;
        }

        $offset = 0;
        $limit = $count > 20000 ? 2000 : 100;

        $chunkNumber = 1;
        $allChunks = $count < $limit ? 1 : ceil($count / $limit);

        while (true) {
            $collection = $this->getEntityManager()->getRepository($matchingData['entity'])
                ->where([$fieldName => null])
                ->limit($offset, $limit)
                ->find();
            if (empty($collection[0])) {
                break;
            }

            if (empty($this->getConfig()->get("matchings.{$matchingData['id']}"))) {
                break;
            }

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'     => "Find matches for {$collection[0]->getEntityName()} ($chunkNumber-$allChunks)",
                'type'     => 'FindMatchesForRecords',
                'status'   => 'Pending',
                'priority' => 20,
                'payload'  => [
                    'matching'    => $matchingData,
                    'entityName'  => $collection[0]->getEntityName(),
                    'entitiesIds' => array_column($collection->toArray(), 'id'),
                ]
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);

            $offset += $limit;
            $chunkNumber++;
        }
    }
}
