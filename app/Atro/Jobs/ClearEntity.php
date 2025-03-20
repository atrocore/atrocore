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

class ClearEntity extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $payload = $job->getPayload();
        $entityName = $payload['entityName'] ?? null;
        if (empty($entityName)) {
            return;
        }

        try {
            $repository = $this->getEntityManager()->getRepository($entityName);
            $repository->clearDeletedRecords($payload['iteration'], $payload['maxPerJob']);
            if (!empty($payload['isLastIteration'])) {
                $repository->clearDeletedRecordsDefinitively();
            }
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("Clear Entity failed for $entityName: {$e->getMessage()}");
        }
    }
}
