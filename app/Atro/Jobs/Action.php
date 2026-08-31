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

class Action extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $job->get('scheduledJobId'));
        if (empty($scheduledJob)) {
            return;
        }

        $actionId = $scheduledJob->get('actionId');
        if (empty($actionId)) {
            return;
        }

        $input = new \stdClass();
        $input->executedViaScheduledJob = true;
        $input->job = $job;

        $this->getServiceFactory()->create('Action')->executeNow($actionId, $input);
    }
}
