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

use Atro\ActionTypes\Update;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotModified;
use Atro\Entities\ActionExecution;
use Atro\Entities\Job;

class MassUpdate extends AbstractJob implements JobInterface
{
    private ?Update $updateAction = null;

    public function run(Job $job): void
    {
        $data = $job->getPayload();

        if (!empty($data['actionExecutionId'])) {
            $this->runForAction($job);
            return;
        }

        if (empty($data['entityType']) || empty($data['total']) || empty($data['ids']) || empty($data['input'])) {
            return;
        }

        $service = $this->getServiceFactory()->create($data['entityType']);

        foreach ($data['ids'] as $id) {
            $input = json_decode(json_encode($data['input']));

            try {
                $service->updateEntity($id, $input);
            } catch (NotModified $e) {
            } catch (\Throwable $e) {
                $message = "Update {$data['entityType']} '$id' failed: {$e->getMessage()}";
                $GLOBALS['log']->error($message);
                $this->createNotification($job, $message);
            }
        }
    }

    public function runForAction(Job $job): void
    {
        $data = $job->getPayload();
        if (empty($data['ids']) || empty($data['actionExecutionId'])) {
            return;
        }

        /** @var ActionExecution $execution */
        $execution = $this->getEntityManager()->getRepository('ActionExecution')->get($data['actionExecutionId']);
        if (empty($execution)) {
            return;
        }

        $action = $execution->get('action');
        if (empty($action)) {
            return;
        }

        $collection = $this
            ->getEntityManager()
            ->getRepository($action->get('searchEntity'))
            ->where(['id' => $data['ids']])
            ->find();

        $input = !empty($data['input']) ? json_decode(json_encode($data['input'])) : new \stdClass();

        $sourceEntity = null;
        if (!empty($data['sourceEntityId']) && !empty($data['sourceEntity'])) {
            $sourceEntity = $this->getEntityManager()->getRepository($data['sourceEntity'])->get($data['sourceEntityId']);
        }

        foreach ($collection as $entity) {
            $this->getUpdateAction()->updateEntity($entity, $sourceEntity, $execution, $input);
        }
    }

    protected function getUpdateAction(): Update
    {
        if ($this->updateAction === null) {
            $className = $this->getMetadata()->get('action.types.update');
            if (empty($className)) {
                throw new Error('Handler for action type "update" not found.');
            }
            $this->updateAction = $this->getContainer()->get($className);
        }

        return $this->updateAction;
    }
}
