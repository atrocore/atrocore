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

use Espo\ORM\Entity;

class Cleanup extends AbstractJob implements JobInterface
{
    public function run(Entity $job): void
    {
        foreach ($this->getMetadata()->get('scopes') as $scopeName => $scopeDefs) {
            /** @var \Espo\ORM\Repository $repository */
            $repository = $this->getEntityManager()->getRepository($scopeName);
            if ($repository->hasDeletedRecordsToCleanup()) {
                $jobEntity = $this->getEntityManager()->getEntity('Job');
                $jobEntity->set([
                    'name'           => "Cleanup $scopeName",
                    'type'           => 'CleanupEntity',
                    'scheduledJobId' => $job->get('scheduledJobId'),
                    'executeTime'    => (new \DateTime())->modify('-1 minute')->format('Y-m-d H:i:s'),
                    'payload'        => [
                        'entityName' => $scopeName
                    ]
                ]);
                $this->getEntityManager()->saveEntity($jobEntity);
            }
        }
    }
}
