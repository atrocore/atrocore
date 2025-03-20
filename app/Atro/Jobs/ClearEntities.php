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

class ClearEntities extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $entities = [];
        foreach ($this->getMetadata()->get('scopes') as $scopeName => $scopeDefs) {
            try {
                if ($this->getEntityManager()->getRepository($scopeName)->hasDeletedRecordsToClear()) {
                    $entities[] = $scopeName;
                }
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Clear failed for $scopeName: {$e->getMessage()}");
            }
        }

        foreach ($entities as $entityName) {
            $count = $this->getEntityManager()->getRepository($entityName)->getNumberOfRecordsToAutoDelete();
            $maxPerJob = 10000;

            $jobCount = max((int)ceil($count / $maxPerJob), 1);
            for ($i = 1; $i <= $jobCount; $i++) {
                $jobEntity = $this->getEntityManager()->getEntity('Job');
                $jobEntity->set([
                    'name'           => "Clear $entityName " . ($jobCount == 1 ? '' : "($i)"),
                    'type'           => 'ClearEntity',
                    'scheduledJobId' => $job->get('scheduledJobId'),
                    'payload'        => [
                        'entityName'      => $entityName,
                        'maxPerJob'       => $maxPerJob,
                        'iteration'       => $i,
                        'isLastIteration' => $i === $jobCount,
                    ]
                ]);
                $this->getEntityManager()->saveEntity($jobEntity);
            }
        }
    }
}
