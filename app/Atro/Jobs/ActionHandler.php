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

use Atro\ActionTypes\AbstractBulkAction;
use Atro\Core\ActionManager;
use Atro\Entities\Job;

class ActionHandler extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        $action = $this->getEntityManager()->getRepository('Action')->get($data['actionId']);
        if (!empty($data['sourceEntity'])) {
            $action->set('sourceEntity', $data['sourceEntity']);
        }

        // execute standalone action in job
        if (empty($data['ids'])) {
            $input = new \stdClass();
            $input->executedViaScheduledJob = true;
            $input->job = $job;
            $input->queueData = $data;
            $this->getActionManager()->executeNow($action, $input);
            return;
        }

        // a chunk of a shared bulk execution (dispatched by an AbstractBulkAction) - reuse the
        // one ActionExecution/its logs instead of creating a fresh execution per id
        if (!empty($data['actionExecutionId'])) {
            $execution = $this->getEntityManager()->getRepository('ActionExecution')->get($data['actionExecutionId']);
            $actionType = $this->getActionType($action->get('type'));

            if (!empty($execution) && $actionType instanceof AbstractBulkAction) {
                $repository = $this->getEntityManager()->getRepository($data['entityType'] ?? $action->get('searchEntity'));

                foreach ($data['ids'] as $id) {
                    $entity = $repository->get($id);
                    if (empty($entity)) {
                        continue;
                    }

                    try {
                        $actionType->runForEntity($entity, $execution);
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error("Mass {$action->get('type')} Action failed for '$id': " . $e->getMessage());
                    }
                }

                return;
            }
        }

        foreach ($data['ids'] as $id) {
            $input = new \stdClass();
            $input->executedViaScheduledJob = true;
            $input->job = $job;
            $input->entityId = $id;
            $input->queueData = $data;

            try {
                $this->getActionManager()->executeNow($action, $input);
            } catch (\Throwable $e) {
                $typeName = ucfirst($action->get('type'));
                $GLOBALS['log']->error("Mass $typeName Action failed: " . $e->getMessage());
            }
        }
    }

    protected function getActionType(string $type)
    {
        $className = $this->getMetadata()->get(['action', 'types', $type]);

        return empty($className) ? null : $this->getContainer()->get($className);
    }

    protected function getActionManager(): ActionManager
    {
        return $this->getContainer()->get('actionManager');
    }
}
