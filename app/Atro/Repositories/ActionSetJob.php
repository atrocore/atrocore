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

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\ORM\Repositories\RDB;
use Espo\ORM\Entity;

class ActionSetJob extends RDB
{
    public function startNextJob(Entity $entity): void
    {
        $child = $this
            ->getEntityManager()
            ->getRepository('ActionSetJob')
            ->where(['parentId' => $entity->get('id')])
            ->findOne();

        if (!empty($child) && $child->get('state') === 'Pending') {
            $input = new \stdClass();
            $input->actionSetJobId = $child->get('id');

            $actionService = $this->getInjection('serviceFactory')->create('Action');
            $actionService->executeNow($entity->get('actionId'), $input);
        }
    }

    public function cancelNextJob(Entity $entity): void
    {
        $children = $entity->get('children');
        if (empty($children) || count($children) === 0) {
            return;
        }

        foreach ($children as $child) {
            $child->set('state', 'Canceled');
            $this->getEntityManager()->saveEntity($child);
            $this->cancelNextJob($child);
        }
    }

    public function executeAction(Entity $entity): void
    {
        $entity->set('state', 'Pending');

        /** @var \Atro\Services\Action $actionService */
        $actionService = $this->getInjection('serviceFactory')->create('Action');

        $input = new \stdClass();
        $input->actionSetJobId = $entity->get('id');

        try {
            $result = $actionService->executeNow($entity->get('actionId'), $input);
        } catch (\Throwable $e) {
            $entity->set('state', 'Failed');
            $entity->set('stateMessage', $e->getMessage());
        }

        $this->save($entity);

        if (empty($result['inBackground'])) {
            $this->startNextJob($entity);
        }
    }

    public function deleteOldJobs(Entity $entity): void
    {
        $jobs = $this
            ->where([
                'actionSetId' => $entity->get('actionSetId'),
                'state'       => [
                    "Done",
                    "Failed",
                    "Canceled"
                ],
                'parentId'    => null
            ])
            ->order('sortOrder', 'DESC')
            ->limit(2000, 200)
            ->find();

        foreach ($jobs as $job) {
            $this->remove($job);
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $entity->set('name', date('Y-m-d H:i:s'));
        }

        if ($entity->isAttributeChanged('state') && $entity->get('state') === 'Canceled' && !in_array($entity->getFetched('state'), ['Pending', 'Running'])) {
            throw new BadRequest('Unexpected job state.');
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('state') && in_array($entity->get('state'), ['Canceled', 'Failed'])) {
            $actionSet = $this->getEntityManager()->getRepository('Action')->get($entity->get('actionSetId'));
            if (empty($actionSet)) {
                throw new Error('No Action found.');
            }

            $this->cancelNextJob($entity);
        }

        $this->deleteOldJobs($entity);

        parent::afterSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $children = $entity->get('children');
        if (!empty($children) && count($children) > 0) {
            foreach ($children as $child) {
                $this->remove($child);
            }
        }

        parent::beforeRemove($entity, $options);
    }

    public function remove(Entity $entity, array $options = [])
    {
        $this->beforeRemove($entity, $options);
        $result = $this->deleteFromDb($entity->get('id'));
        if ($result) {
            $this->afterRemove($entity, $options);
        }
        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
        $this->addDependency('eventManager');
    }
}
