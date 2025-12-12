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

use Atro\Core\Exceptions\Error;
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

        return $this->createEntity($entity, $action, $input);
    }

    protected function createEntity(?Entity $entity, Entity $action, \stdClass $input): bool
    {
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
                        throw new Error("Action '{$action->get('name')}' failed. Script generated invalid JSON: $outputJson");
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
            $existed = $this->getEntityManager()->getEntity($action->get('targetEntity'), $inputData->id);
            if (!empty($existed)) {
                $this->updateTargetEntity($existed->id, $inputData, $action);
                return true;
            }
        }

        $this->getService($action->get('targetEntity'))->createEntity($inputData);

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