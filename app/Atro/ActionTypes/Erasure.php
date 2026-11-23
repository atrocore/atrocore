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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Services\Interfaces\PersonalDataErasableInterface;
use Atro\DTOs\ActionResultDTO;
use Atro\Entities\ActionExecution;
use Espo\ORM\Entity;

/**
 * Erases the personal data of the selected or found records of the target entity according to the erasure mode
 * of each field.
 */
class Erasure extends AbstractAction
{
    public function useMassActions(Entity $action, \stdClass $input): bool
    {
        return false;
    }

    public function execute(ActionExecution $execution, \stdClass $input): bool
    {
        return $this->executeWithResult($execution, $input)->toBool();
    }

    public function executeWithResult(ActionExecution $execution, \stdClass $input): ActionResultDTO
    {
        $action = $execution->get('action');

        // the data are erased without a user action, if the action is not executed by the user manually
        $automatically = !empty($execution->get('type')) && $execution->get('type') !== 'manual';

        if (property_exists($input, 'where') || empty($action->getSubtype()?->get('applyToPreselectedRecords'))) {
            if (property_exists($input, 'where')) {
                $where = json_decode(json_encode($input->where), true);
            } else {
                $whereJson = $this->getTwig()->renderTemplate(json_encode($this->getWhere($action) ?? []), [
                    'entity' => $this->getSourceEntity($action, $input),
                ]);
                $where = @json_decode($whereJson, true);
            }

            $entityType = (string)($action->getSubtype()?->get('searchEntity') ?? $action->getSubtype()?->get('targetEntity'));

            $res = $this->getErasableService($entityType)->massErasePersonalData([
                'where'         => $where ?? [],
                'automatically' => $automatically,
            ]);

            if (!empty($res['sync'])) {
                return ActionResultDTO::done();
            }

            // the erasure job is not linked to the execution, the execution is finished once the job is created
            $this->markExecutionDone($execution);

            return ActionResultDTO::queued();
        }

        $sourceEntity = null;
        if (!empty($action->get('sourceEntity')) && property_exists($input, 'entityId')) {
            $sourceEntity = $this->getEntityManager()->getRepository($action->get('sourceEntity'))->get($input->entityId);
        }

        if (empty($sourceEntity)) {
            throw new BadRequest('Action can be executed only from Source Entity.');
        }

        $res = $this->getErasableService($sourceEntity->getEntityType())->erasePersonalData($sourceEntity->get('id'), $automatically);

        return ActionResultDTO::done($res);
    }

    protected function getErasableService(string $entityType): PersonalDataErasableInterface
    {
        $service = null;
        if (!empty($entityType) && !empty($this->getMetadata()->get(['scopes', $entityType, 'containsPersonalData']))) {
            $service = $this->getServiceFactory()->create($entityType);
        }

        if (!$service instanceof PersonalDataErasableInterface) {
            throw new BadRequest(sprintf($this->container->get('language')->translate('entityDoesNotContainPersonalData', 'exceptions', 'Action'), $entityType));
        }

        return $service;
    }
}
