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

class MassRestore extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();
        if (empty($data['entityType']) || empty($data['total']) || empty($data['ids'])) {
            return;
        }

        $entityType = $data['entityType'];
        $service = $this->getServiceFactory()->create($entityType);

        foreach ($data['ids'] as $id) {
            try {
                $service->restoreEntity($id);
            } catch (\Throwable $e) {
                $message = "Restore {$entityType} '$id' failed: {$e->getTraceAsString()}";
                $GLOBALS['log']->error($message);
                $this->createNotification($job, $message);
            }
        }
    }
}
