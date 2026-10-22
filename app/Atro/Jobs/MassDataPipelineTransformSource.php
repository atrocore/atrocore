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
use Atro\Services\DataPipeline;

class MassDataPipelineTransformSource extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        if (empty($data['entityType']) || empty($data['ids']) || empty($data['targetEntityName'])) {
            return;
        }

        /** @var DataPipeline $service */
        $service = $this->getServiceFactory()->create('DataPipeline');

        $count = 0;

        foreach ($data['ids'] as $id) {
            try {
                $service->transformSourceRecord($data['entityType'], $data['targetEntityName'], $id);
                $count++;
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Transform {$data['entityType']} '$id' failed: {$e->getMessage()}");
            }
        }

        $total = count($data['ids']);
        if ($count < $total) {
            $job->set('message', sprintf($this->translate('recordsTransformed', 'messages', 'DataPipeline'), $count, $total) . "\n" . sprintf($this->translate('recordsNotTransformed', 'messages', 'DataPipeline'), $total - $count));
        }
    }
}
