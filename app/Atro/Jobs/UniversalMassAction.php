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

use Atro\Core\Exceptions\Error;
use Atro\Entities\Job;
use Atro\Services\ClusterItem;

class UniversalMassAction extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();
        if (empty($data['entityType']) || empty($data['total']) || empty($data['ids']) || empty($data['singleActionMethod'])) {
            return;
        }

        $entityType = $data['entityType'];
        $singleActionMethod = $data['singleActionMethod'];
        $action = $data['action'] ?? null;

        /** @var ClusterItem $service */
        $service = $this->getServiceFactory()->create($entityType);

        if(!method_exists($service, $singleActionMethod)) {
            throw  new Error($singleActionMethod. ' method does not exist in the service '.$entityType);
        }

        foreach ($data['ids'] as $id) {
            try {
                $service->{$singleActionMethod}($id);
            } catch (\Throwable $e) {
                $message = ucfirst($action)." {$entityType} '$id' failed: {$e->getTraceAsString()}";
                $GLOBALS['log']->error($message);
                $this->createNotification($job, $message);
            }
        }
    }
}
