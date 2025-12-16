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

namespace Atro\ActionTypes;

use Atro\Core\Exceptions\NotModified;
use Atro\Core\Exceptions\NotUnique;
use Atro\Entities\ActionExecution;
use Espo\ORM\Entity;
use Atro\Services\Record;

class Create extends AbstractAction
{
    protected array $services = [];

    public function execute(ActionExecution $execution, \stdClass $input): bool
    {
        $action = $execution->get('action');

        if (!empty($searchEntityName = $action->get('searchEntity'))) {
            /** @var \Espo\Core\SelectManagers\Base $selectManager */
            $selectManager = $this->container->get('selectManagerFactory')->create($searchEntityName);

            /** @var \Atro\Core\Templates\Repositories\Base $repository */
            $repository = $this->getEntityManager()->getRepository($searchEntityName);

            $where = json_decode(json_encode($this->getWhere($action) ?? []), true);

            $selectParams = $selectManager->getSelectParams(['where' => $where], true, true);
            $repository->handleSelectParams($selectParams);
            $count = $repository->count($selectParams);

            if ($count === 0) {
                return true;
            }

            $offset = 0;
            $limit = $this->getConfig()->get('massCreateMaxChunkSize', 1);

            if ($count >= $limit) {
                while (true) {
                    $collection = $repository
                        ->select(['id'])
                        ->limit($offset, $limit)
                        ->order('id', 'ASC')
                        ->find($selectParams);

                    if (empty($collection[0])) {
                        break;
                    }

                    $offset = $offset + $limit;

                    $jobEntity = $this->getEntityManager()->getEntity('Job');
                    $jobEntity->set([
                        'name'    => "Mass create of '{$action->get('targetEntity')}'",
                        'type'    => 'MassCreate',
                        'status'  => 'Pending',
                        'payload' => [
                            'ids'               => array_column($collection->toArray(), 'id'),
                            'actionExecutionId' => $execution->get('id'),
                            'input'             => $input,
                        ]
                    ]);
                    $this->getEntityManager()->saveEntity($jobEntity);
                }
                return true;
            } else {
                foreach ($repository->find($selectParams) as $entity) {
                    $this->createEntity($entity, $action, $input);
                }
            }
        } else {
            $entity = null;
            if (property_exists($input, 'triggeredEntity')) {
                $entity = $input->triggeredEntity;
            } elseif (property_exists($input, 'triggeredEntityType') && property_exists($input, 'triggeredEntityId')) {
                $entity = $this->getEntityManager()->getRepository($input->triggeredEntityType)->get($input->triggeredEntityId);
                if (empty($entity)) {
                    $execution->set('status', 'done');
                    $this->getEntityManager()->saveEntity($execution);

                    return false;
                }
            } elseif (!empty($action->get('sourceEntity')) && property_exists($input, 'entityId')) {
                $entity = $this->getEntityManager()->getRepository($action->get('sourceEntity'))->get($input->entityId);
                if (empty($entity)) {
                    $execution->set('status', 'done');
                    $this->getEntityManager()->saveEntity($execution);

                    return false;
                }
            }

            $this->createEntity($entity, $action, $input);
        }

        $execution->set('status', 'done');
        $this->getEntityManager()->saveEntity($execution);

        return true;
    }

    public function createEntity(?Entity $entity, Entity $action, \stdClass $input): bool
    {
        $targetEntityName = $action->get('targetEntity');
        $actionData = $action->get('data');

        if (empty($actionData->field) || empty($actionData->field->updateType)) {
            return false;
        }

        $inputData = null;
        switch ($actionData->field->updateType) {
            case 'basic':
                $inputData = $actionData->fieldData ?? null;
                break;
            case 'script':
                if (!empty($actionData->field->updateScript)) {
                    $templateData = [
                        'entity'              => $entity,
                        'triggeredEntityType' => $input->triggeredEntityType ?? null,
                        'triggeredEntityId'   => $input->triggeredEntityId ?? null,
                    ];
                    $outputJson = $this->container->get('twig')
                        ->renderTemplate($actionData->field->updateScript, $templateData);
                    $input = @json_decode((string)$outputJson);
                    if ($input === null) {
                        $GLOBALS['log']->error("Action '{$action->get('name')}' failed. Script generated invalid JSON: $outputJson");
                        return false;
                    }
                    $inputData = $input;
                }
                break;
        }

        if ($inputData === null) {
            return false;
        }

        $inputData->_workflowAction = true;

        if (property_exists($inputData, 'id')) {
            $existed = $this->getEntityManager()->getEntity($targetEntityName, $inputData->id);
            if (!empty($existed)) {
                if ($action->get('type') === 'createOrUpdate') {
                    try {
                        $this->getService($targetEntityName)->updateEntity($existed->id, $inputData);
                    } catch (NotModified $e) {
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error("Update of '{$targetEntityName}: $existed->id' failed: {$e->getMessage()}");
                    }
                }
                return true;
            }
        }

        try {
            $this->getService($targetEntityName)->createEntity($inputData);
        } catch (NotUnique $e) {
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("Create of '$targetEntityName' failed: {$e->getMessage()}");
        }

        return true;
    }

    protected function getService(string $name): Record
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this->getServiceFactory()->create($name);
        }

        return $this->services[$name];
    }
}