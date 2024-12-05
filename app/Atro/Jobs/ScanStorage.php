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

class ScanStorage extends AbstractJob implements JobInterface
{
    public function run(Entity $job): void
    {
        if (empty($job->get('scheduledJobId'))) {
            return;
        }

        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $job->get('scheduledJobId'));
        if (empty($scheduledJob)) {
            return;
        }

        $storageId = $scheduledJob->get('storageId');
        if (empty($storageId)) {
            return;
        }

        $this->getServiceFactory()->create('Storage')->createScanJob($storageId, false);
    }
}
