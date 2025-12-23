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

class FindMatches extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $exists = $this->getEntityManager()->getRepository('Job')
            ->where([
                'id!='   => $job->id,
                'type'   => 'FindMatches',
                'status' => 'Running'
            ])
            ->findOne();

        if (!empty($exists)) {
            return;
        }

        foreach ($this->getEntityManager()->getRepository('Matching')->find() as $matching) {
            if (empty($matching->get('isActive'))) {
                continue;
            }

            if (empty($matching->get('matchingRules')[0])) {
                continue;
            }

            $fieldName = \Atro\Repositories\Matching::prepareFieldName($matching->id);

            $offset = 0;
            $limit = 5000;

            $checkJobInterval = ($this->getConfig()->get('maxConcurrentWorkers') ?? 6) * 2;

            while (true) {
                $collection = $this->getEntityManager()->getRepository($matching->get('entity'))
                    ->where([$fieldName => false])
                    ->limit($offset, $limit)
                    ->find();
                if (empty($collection[0])) {
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
                            'matching'   => $matching->toPayload(),
                            'entityName' => $entity->getEntityName(),
                            'entityId'   => $entity->id
                        ]
                    ]);
                    $this->getEntityManager()->saveEntity($jobEntity);

                    if (!empty($matching->get('matchedRecordsMax')) && $k % $checkJobInterval === 0) {
                        $jobEntity = $this->getEntityManager()->getEntity('Job');
                        $jobEntity->set([
                            'name'     => "Check whether the searching of the matches for '{$entity->getEntityName()}' needs to stop",
                            'type'     => 'StopFindingMatches',
                            'status'   => 'Pending',
                            'priority' => 20,
                            'payload'  => [
                                'matching'   => $matching->toPayload(),
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
}
