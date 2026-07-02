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
use Doctrine\DBAL\ParameterType;
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

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (in_array($entity->get('status'), ['done', 'failed']) && $entity->get('type') === 'manual') {
            $notification = $this->getEntityManager()->getEntity('Notification');
            $notification->set('type', 'Message');
            $notification->set('message', sprintf($this->getLanguage()->translate('actionExecutionFinished', 'notifications', 'ActionExecution'), $entity->get('name'), $entity->id));

            $notification->set('relatedType', $entity->getEntityName());
            $notification->set('relatedId', $entity->get('id'));
            $notification->set('userId', $entity->get('createdBy')->get('delegatorId'));

            $this->getEntityManager()->saveEntity($notification);
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

    public function isWorkflowLooping(string $workflowId, \stdClass $input): bool
    {
        $entityType  = $input->triggeredEntityType ?? null;
        $entityId    = $input->triggeredEntityId ?? null;

        if (empty($entityType) || empty($entityId)) {
            return false;
        }

        $threshold     = (int)$this->getConfig()->get('workflowLoopThreshold', 5);
        $windowMinutes = (int)$this->getConfig()->get('workflowLoopWindowMinutes', 5);
        $since         = (new \DateTime("-{$windowMinutes} minutes"))->format('Y-m-d H:i:s');

        $count = $this->getDbal()->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('action_execution')
            ->where('workflow_id = :workflowId')
            ->andWhere('payload LIKE :entityType')
            ->andWhere('payload LIKE :entityId')
            ->andWhere('payload LIKE :importJobExists')
            ->andWhere('deleted = :false')
            ->andWhere('created_at > :since')
            ->setParameter('workflowId', $workflowId)
            ->setParameter('entityType', '%"triggeredEntityType":"' . $entityType . '"%')
            ->setParameter('entityId', '%"triggeredEntityId":"' . $entityId . '"%')
            ->setParameter('importJobExists', '%"importJobId":%')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('since', $since)
            ->fetchOne();

        return (int)$count >= $threshold;
    }
}
