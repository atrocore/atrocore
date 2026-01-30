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

namespace Atro\Services;

use Atro\Core\Templates\Services\Base;
use Atro\Entities\ActionExecution as ActionExecutionEntity;
use Espo\ORM\Entity;

class ActionExecution extends Base
{
    protected $mandatorySelectAttributeList = ['actionId', 'status', 'statusMessage', 'createdCount', 'updatedCount', 'failedCount'];

    /**
     * @param ActionExecutionEntity $entity
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $action = $this->getEntityManager()->getRepository('Action')->get($entity->get('actionId'));
        if (!empty($action)) {
            // put object into entity
            $entity->set('action', $action);
            $entity->set('actionName', $action->get('name'));

            if (in_array($action->get('type'), ['create', 'update', 'createOrUpdate'])) {
                $entity->set('listScope', $action->get('targetEntity'));
                $this->getRepository()->prepareCount($entity, 'createdCount');
                $this->getRepository()->prepareCount($entity, 'updatedCount');
                $this->getRepository()->prepareCount($entity, 'failedCount');
            }
        }
    }

    public function putAclMetaForLink(Entity $entityFrom, string $link, Entity $entity): void
    {
        if ($entityFrom->getEntityName() !== 'Action' || $link !== 'executions') {
            parent::putAclMetaForLink($entityFrom, $link, $entity);
            return;
        }

        $this->putAclMeta($entity);

        $entity->setMetaPermission('allLogs', true);
    }
}
