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

use Atro\ActionTypes\AbstractAction;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class Delete extends AbstractAction
{
    public function useMassActions(Entity $action, \stdClass $input): bool
    {
        return false;
    }

    public function executeNow(Entity $action, \stdClass $input): bool
    {
        if (property_exists($input, 'where')) {
            $res = $this->getServiceFactory()->create($action->get('searchEntity'))->massRemove([
                'where' => json_decode(json_encode($input->where), true)
            ]);
            return !empty($res);
        }

        $sourceEntity = null;
        if (!empty($action->get('sourceEntity')) && property_exists($input, 'entityId')) {
            $sourceEntity = $this->getEntityManager()->getRepository($action->get('sourceEntity'))->get($input->entityId);
            if (empty($sourceEntity)) {
                return false;
            }
        }

        if (empty($action->get('applyToPreselectedRecords'))) {
            $whereJson = json_encode($this->getWhere($action) ?? []);

            $templateData = [
                'entity' => null
            ];

            if (!empty($input->triggeredEntity)) {
                $templateData['entity'] = $input->triggeredEntity;
            } else if (property_exists($input, 'triggeredEntityType') && property_exists($input, 'triggeredEntityId')) {
                $templateData['entity'] = $this->getEntityManager()->getRepository($input->triggeredEntityType)->get($input->triggeredEntityId);
            }

            $whereJson = $this->container->get('twig')->renderTemplate($whereJson, $templateData);
            $where = @json_decode($whereJson, true);

            $res = $this->getServiceFactory()->create($action->get('searchEntity'))->massRemove([
                'where' => $where
            ]);

            return !empty($res);
        } else {
            if (!property_exists($input, 'entityId') || empty($sourceEntity)) {
                throw new BadRequest('Action can be executed only from Source Entity.');
            }
        }

        return $this->getServiceFactory()->create($sourceEntity->getEntityType())->deleteEntity($sourceEntity->get('id'));
    }
}