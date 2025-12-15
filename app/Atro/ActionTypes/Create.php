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

use Atro\Core\Exceptions\NotUnique;
use Espo\ORM\Entity;
use Atro\Services\Record;

class Create extends AbstractAction
{
    protected array $services = [];

    public function executeNow(Entity $action, \stdClass $input): bool
    {
        $entity = null;
        if (property_exists($input, 'triggeredEntity')) {
            $entity = $input->triggeredEntity;
        } elseif (property_exists($input, 'triggeredEntityType') && property_exists($input, 'triggeredEntityId')) {
            $entity = $this->getEntityManager()->getRepository($input->triggeredEntityType)->get($input->triggeredEntityId);
            if (empty($entity)) {
                return false;
            }
        } elseif (!empty($action->get('sourceEntity')) && property_exists($input, 'entityId')) {
            $entity = $this->getEntityManager()->getRepository($action->get('sourceEntity'))->get($input->entityId);
            if (empty($entity)) {
                return false;
            }
        }

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
            $limit = $this->getConfig()->get('massCreateMaxChunkSize', 3000);

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

                    $jobEntity = $this->getEntityManager()->getEntity('Job');
                    $jobEntity->set([
                        'name'    => "Mass create of '{$entity->getEntityName()}'",
                        'type'    => 'MassCreate',
                        'status'  => 'Pending',
                        'payload' => [
                            'ids'        => array_column($collection->toArray(), 'id'),
                            'entityName' => $searchEntityName,
                            'actionId'   => $action->get('id'),
                            'input'      => $input,
                        ]
                    ]);
                    $this->getEntityManager()->saveEntity($jobEntity);
                }
            } else {
                foreach ($repository->find($selectParams) as $entity) {
                    $this->createEntity($entity, $action, $input);
                }
            }
        }

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
                $this->updateTargetEntity($existed->id, $inputData, $action);
                return true;
            }
        }

        try {
            $this->getService($targetEntityName)->createEntity($inputData);
        } catch (NotUnique $e) {
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("Create Action for '$targetEntityName' failed: {$e->getMessage()}");
        }

        return true;
    }

    protected function updateTargetEntity(string $id, \stdClass $input, Entity $action): void
    {
        // avoid update because it's only create
    }

    protected function getService(string $name): Record
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this->getServiceFactory()->create($name);
        }

        return $this->services[$name];
    }
}