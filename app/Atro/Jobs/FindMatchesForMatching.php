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

        $offset = 0;
        $limit = 5000;

        $checkJobInterval = ($this->getConfig()->get('maxConcurrentWorkers') ?? 6) * 2;

        while (true) {
            $collection = $this->getEntityManager()->getRepository($matchingData['entity'])
                ->where([$fieldName => false])
                ->limit($offset, $limit)
                ->find();
            if (empty($collection[0])) {
                break;
            }

            if (empty($this->getConfig()->get("matchings.{$matchingData['id']}"))) {
                break;
            }

            foreach ($collection as $k => $entity) {
                $jobEntity = $this->getEntityManager()->getEntity('Job');
                $jobEntity->set([
                    'name'     => "Find matches for {$entity->getEntityName()}: {$entity->get('name')}",
                    'type'     => 'FindMatchesForRecord',
                    'status'   => 'Pending',
                    'priority' => 20,
                    'payload'  => [
                        'matching'   => $matchingData,
                        'entityName' => $entity->getEntityName(),
                        'entityId'   => $entity->id
                    ]
                ]);
                $this->getEntityManager()->saveEntity($jobEntity);

                if (!empty($matchingData['matchedRecordsMax']) && $k % $checkJobInterval === 0) {
                    $jobEntity = $this->getEntityManager()->getEntity('Job');
                    $jobEntity->set([
                        'name'     => "Check whether the searching of the matches for '{$entity->getEntityName()}' needs to stop",
                        'type'     => 'StopFindingMatches',
                        'status'   => 'Pending',
                        'priority' => 20,
                        'payload'  => [
                            'matching'   => $matchingData,
                            'entityName' => $entity->getEntityName()
                        ]
                    ]);
                    $this->getEntityManager()->saveEntity($jobEntity);
                }
            }

            $offset += $limit;
        }
    }
}
