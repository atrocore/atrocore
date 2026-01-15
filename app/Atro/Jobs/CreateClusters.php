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

class CreateClusters extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $exists = $this->getEntityManager()->getRepository('Job')
            ->where([
                'id!='   => $job->id,
                'type'   => 'CreateClusters',
                'status' => 'Running'
            ])
            ->findOne();

        if (!empty($exists)) {
            return;
        }

        $masterEntities = [];
        foreach ($this->getEntityManager()->getRepository('Matching')->find() as $matching) {
            $masterEntities[$this->getMetadata()->get("scopes.{$matching->get('masterEntity')}.primaryEntityId") ?? $matching->get('masterEntity')] = true;
        }

        foreach ($masterEntities as $masterEntity => $v) {
            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'     => "Create Clusters for {$masterEntity}",
                'type'     => 'CreateClustersForMasterEntity',
                'status'   => 'Pending',
                'priority' => 20,
                'payload'  => [
                    'masterEntity' => $masterEntity
                ]
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);
        }
    }
}
