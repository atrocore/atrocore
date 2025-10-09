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

class CalculateScriptFieldsForEntities extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        foreach ($this->getMetadata()->get('scopes') as $entityName => $scopeDefs) {

            if ($scopeDefs['type'] === 'ReferenceData') {
                continue;
            }

            $offset = 0;
            $limit = 5000;
            $records = [true];

            while (!empty($records)) {
                $ids = $this->getEntityManager()->getRepository($entityName)->getEntitiesIdsWithNullScriptFields($offset, $limit);

                if (empty($ids)) {
                    break;
                }

                $last = $offset + count($ids);
                $jobEntity = $this->getEntityManager()->getEntity('Job');
                $jobEntity->set([
                    'name' => "Calculate script fields of $entityName ($offset - $last)",
                    'type' => 'CalculateScriptFieldsForEntity',
                    'priority' => 20,
                    'scheduledJobId' => $job->get('scheduledJobId'),
                    'payload' => [
                        'scope' => $entityName,
                        'ids' => $ids,
                    ]
                ]);
                $this->getEntityManager()->saveEntity($jobEntity);
                $offset += $limit;
            }
        }
    }
}
