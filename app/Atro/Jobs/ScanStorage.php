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

class ScanStorage extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $payload = $job->getPayload();

        if (!empty($job->get('scheduledJobId'))) {
            $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $job->get('scheduledJobId'));
            if (empty($scheduledJob)) {
                return;
            }
            $storageId = $scheduledJob->get('storageId');
        } else {
            $storageId = $payload['storageId'] ?? null;
        }

        if (empty($storageId)) {
            return;
        }

        $storage = $this->getEntityManager()->getEntity('Storage', $storageId);
        if (empty($storage) || empty($storage->get('isActive'))) {
            return;
        }

        $this->getContainer()->get($storage->get('type') . 'Storage')->scan($storage);

        if (!empty($payload['manual'])) {
            $message = sprintf($this->translate('scanDone', 'labels', 'Storage'), $storage->get('name'));
            $this->createNotification($job, $message);
        }
    }
}
