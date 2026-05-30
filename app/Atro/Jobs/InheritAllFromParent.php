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

class InheritAllFromParent extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        if (empty($data['entityType']) || empty($data['ids'])) {
            return;
        }

        $service = $this->getServiceFactory()->create($data['entityType']);

        foreach ($data['ids'] as $id) {
            try {
                $service->inheritAllFromParent($id);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Inherit from parent failed for {$data['entityType']} $id' failed: {$e->getMessage()}");
            }
        }
    }
}
