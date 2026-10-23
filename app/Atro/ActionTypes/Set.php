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

namespace Atro\ActionTypes;

use Atro\DTOs\ActionResultDTO;
use Atro\Entities\ActionExecution;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class Set extends AbstractAction
{
    public function execute(ActionExecution $execution, \stdClass $input): bool
    {
        return $this->executeWithResult($execution, $input)->toBool();
    }

    /**
     * @deprecated Use executeWithResult(). Kept for backward compatibility.
     * @todo 3.0.0: remove, executeWithResult() becomes execute().
     */
    public function executeNow(Entity $action, \stdClass $input): bool
    {
        return $this->execute($this->createFakeExecution($action), $input);
    }

    public function executeWithResult(ActionExecution $execution, \stdClass $input): ActionResultDTO
    {
        $action = $execution->get('action');

        $linker = $this->getEntityManager()->getRepository('ActionSetLinker')
            ->where([
                'setId'    => $action->get('id'),
                'isActive' => true
            ])
            ->order('sortOrder', 'ASC')
            ->findOne();

        if (empty($linker)) {
            return ActionResultDTO::skipped('The action set has no active actions.');
        }

        $result = $this->runAction($linker, $input);

        // the set only waits for the jobs, its execution is done
        if ($result->inBackground !== false) {
            $this->markExecutionDone($execution);
        }

        return $result;
    }

    public function executeAction(Entity $current, \stdClass $input): bool
    {
        return $this->runAction($current, $input)->toBool();
    }

    /**
     * Executes the action of the given step and, when the step did not hand its work over to background jobs,
     * the following steps. When a step did, the set continues from Set::checkJob once the jobs are finished.
     *
     * The result is the result of the last step that ran: "in background" means the set is waiting for jobs.
     */
    public function runAction(Entity $current, \stdClass $input): ActionResultDTO
    {
        if ($current->getEntityName() != 'ActionSetLinker') {
            return ActionResultDTO::skipped();
        }

        if (empty($action = $current->get('action'))) {
            return ActionResultDTO::skipped();
        }

        if (!empty($input->triggeredEntityType) && !empty($action->get('sourceEntity'))) {
            $action->set('sourceEntity', $input->triggeredEntityType);
        }

        if (property_exists($input, 'actionSetLinkerId')) {
            unset($input->actionSetLinkerId);
        }

        // the selection the set was started with, every action of the set has to be able to start from it
        if (empty($input->actionSetInitialInput)) {
            $input->actionSetInitialInput = $this->prepareInitialInput($input);
        }

        $input->actionSetLinkerId = $current->get('id');

        $result = $this->getActionManager()->execute($action, $input);

        if (!$this->waitsForJobs($result) && !empty($next = $this->getNextAction($current))) {
            $nextResult = $this->runAction($next, $input);

            // nothing ran after this step => the result of this step is the result of the set
            if (!$nextResult->executed) {
                return $result;
            }

            // a failed step must not be hidden by the steps that ran after it
            $success = (!$result->executed || $result->success) && $nextResult->success;

            return new ActionResultDTO(true, $nextResult->inBackground, $success, $nextResult->message ?? $result->message);
        }

        return $result;
    }

    /**
     * True when the step handed its work over to background jobs, the next step has to wait for them.
     * An action type that does not report it (inBackground is null) is considered as finished.
     */
    protected function waitsForJobs(ActionResultDTO $result): bool
    {
        return $result->executed && $result->inBackground === true;
    }

    public function getNextAction(Entity $entity): ?Entity
    {
        if ($entity->getEntityType() != 'ActionSetLinker') {
            return null;
        }

        return $this
            ->getEntityManager()
            ->getRepository('ActionSetLinker')
            ->where([
                'setId'      => $entity->get('setId'),
                'sortOrder>' => $entity->get('sortOrder'),
                'isActive'   => true
            ])
            ->order('sortOrder', 'ASC')
            ->findOne();
    }

    public function checkJob(Entity $entity): void
    {
        if ($entity->getEntityType() !== 'Job') {
            return;
        }

        if (!preg_match("/\"actionSetLinkerId\":\"([a-z0-9]*)\"/", json_encode($entity->get('payload')), $matches)) {
            return;
        }

        $current = $this->getEntityManager()->getEntity('ActionSetLinker', $matches[1]);
        if (empty($current)) {
            return;
        }

        $exist = $this
            ->getEntityManager()
            ->getRepository('Job')
            ->where([
                'status'   => ['Pending', 'Running'],
                'payload*' => '%"actionSetLinkerId":"' . $current->get('id') . '"%'
            ])
            ->find();

        if (count($exist) == 0 && !empty($next = $this->getNextAction($current))) {
            $data = $entity->get('payload');
            $data = empty($data) ? [] : Json::decode(Json::encode($data), true);

            // continue with the selection the set was started with, an action can have its own filter (where) in the payload
            $initialInput = $this->searchValueByKey($data, 'actionSetInitialInput');
            if (!empty($initialInput)) {
                $this->executeAction($next, (object)$initialInput);
            } else {
                $where = $this->searchValueByKey($data, 'where');

                $this->executeAction($next, (object)['where' => $where]);
            }
        }
    }

    protected function prepareInitialInput(\stdClass $input): array
    {
        $result = [];
        foreach (['entityId', 'where', 'triggeredEntityType', 'triggeredEntityId'] as $key) {
            if (property_exists($input, $key) && $input->$key !== null && $input->$key !== '' && $input->$key !== []) {
                $result[$key] = json_decode(json_encode($input->$key), true);
            }
        }

        return $result;
    }

    protected function searchValueByKey($array, $key)
    {
        if (!is_array($array)) {
            return [];
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach ($array as $subArray) {
            if (is_array($subArray)) {
                $result = $this->searchValueByKey($subArray, $key);
                if ($result !== []) {
                    return $result;
                }
            }
        }

        return [];
    }
}
