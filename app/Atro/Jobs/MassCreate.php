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

use Atro\ActionTypes\Create;
use Atro\Core\Exceptions\Error;
use Atro\Entities\ActionExecution;
use Atro\Entities\Job;

class MassCreate extends AbstractJob implements JobInterface
{
    private ?Create $createAction = null;

    public function run(Job $job): void
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

        foreach ($collection as $entity) {
            $this->getCreateAction()->createEntity($entity, $execution, $input);
        }
    }

    protected function getCreateAction(): Create
    {
        if ($this->createAction === null) {
            $className = $this->getMetadata()->get('action.types.create');
            if (empty($className)) {
                throw new Error('Handler for action type "create" not found.');
            }
            $this->createAction = $this->getContainer()->get($className);
        }

        return $this->createAction;
    }
}
