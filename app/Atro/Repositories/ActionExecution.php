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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Atro\Entities\ActionExecution as ActionExecutionEntity;
use Espo\ORM\Entity;

class ActionExecution extends Base
{
    /**
     * @param ActionExecutionEntity $entity
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->get('status') === 'running') {
            $entity->set('startedAt', (new \DateTime())->format('Y-m-d H:i:s'));
        }

        if (in_array($entity->get('status'), ['done', 'failed'])) {
            $entity->set('finishedAt', (new \DateTime())->format('Y-m-d H:i:s'));
        }
    }

    public function prepareCount(ActionExecutionEntity $execution, string $field): void
    {
        if ($execution->get($field) !== null) {
            return;
        }

        $action = $execution->get('action');

        switch ($field) {
            case 'createdCount':
                $execution->set($field, $action->get('type') === 'update' ? 0 : $this->calculateCount($execution->get('id'), 'create'));
                break;
            case 'updatedCount':
                $execution->set($field, $action->get('type') === 'create' ? 0 : $this->calculateCount($execution->get('id'), 'update'));
                break;
            case 'failedCount':
                $execution->set($field, $this->calculateCount($execution->get('id'), 'error'));
                break;
        }

        if ($execution->get('status') !== 'running') {
            $this->getConnection()->createQueryBuilder()
                ->update('action_execution')
                ->set(Util::toUnderScore($field), ":$field")
                ->where('id=:id')
                ->setParameter('id', $execution->get('id'))
                ->setParameter($field, $execution->get($field))
                ->executeQuery();
        }
    }

    public function calculateCount(string $actionExecutionId, string $type): int
    {
        return $this->getEntityManager()->getRepository('ActionExecutionLog')
            ->select(['id'])
            ->where(['type' => $type, 'actionExecutionId' => $actionExecutionId])
            ->count();
    }
}
