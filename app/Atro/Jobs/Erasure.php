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

use Atro\Core\Services\Interfaces\PersonalDataErasableInterface;
use Atro\Entities\Job;

class Erasure extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $job->get('scheduledJobId'));
        if (empty($scheduledJob)) {
            return;
        }

        $entityName = $scheduledJob->get('entity');
        if (empty($entityName) || empty($this->getMetadata()->get(['scopes', $entityName, 'containsPersonalData']))) {
            return;
        }

        $service = $this->getServiceFactory()->create($entityName);
        if (!$service instanceof PersonalDataErasableInterface) {
            return;
        }

        $searchFilter = @json_decode(json_encode($scheduledJob->get('searchFilter')), true);

        $service->massErasePersonalData([
            'where'         => $searchFilter['where'] ?? [],
            'automatically' => true,
        ]);
    }
}
