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

class ScanStorage extends AbstractJob
{
    public function run($data, $targetId, $targetType, $scheduledJobId): bool
    {
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $scheduledJobId);
        if (empty($scheduledJob)) {
            return false;
        }

        $storageId = $scheduledJob->get('storageId');
        if (empty($storageId)) {
            return false;
        }

        return $this->getServiceFactory()->create('Storage')->createScanJob($storageId, false);
    }
}
