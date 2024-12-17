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

namespace Atro\Repositories;

use Atro\ActionTypes\Set;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\JobManager;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Job extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (empty($entity->get('executeTime'))) {
            $entity->set('executeTime', date('Y-m-d H:i:s'));
        }

        if (empty($entity->get('ownerUserId'))) {
            $entity->set('ownerUserId', $this->getEntityManager()->getUser()->get('id'));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('status')) {
            if ($entity->get('status') === 'Pending') {
                $entity->set('pid', null);
                $entity->set('startedAt', null);
                $entity->set('endedAt', null);
                $entity->set('message', null);
            }

            if (!$entity->isNew() && !empty($entity->getFetched('status'))) {
                $transitions = [
                    "Running"  => [
                        "Pending"
                    ],
                    "Success"  => [
                        "Running"
                    ],
                    "Canceled" => [
                        "Pending",
                        "Running"
                    ],
                    "Awaiting" => [],
                    "Failed"   => [
                        "Pending",
                        "Running"
                    ],
                    "Pending"  => [
                        "Success",
                        "Canceled",
                        "Failed"
                    ]
                ];

                if (!isset($transitions[$entity->get('status')])) {
                    throw new Error("Unknown status '{$entity->get('status')}'.");
                }

                if (!in_array($entity->getFetched('status'), $transitions[$entity->get('status')])) {
                    throw new Error("It is impossible to change the status from '{$entity->getFetched('status')}' to '{$entity->get('status')}'.");
                }
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->get('status') === 'Pending' && !empty($entity->get('type'))) {
            file_put_contents(JobManager::QUEUE_FILE, '1');
        }

        if ($entity->isAttributeChanged('status')) {
            if ($entity->get('status') === 'Canceled' && !empty($entity->get('pid'))) {
                exec("kill -9 {$entity->get('pid')}");
            }

            if (in_array($entity->get('status'), ['Success', 'Failed'])) {
                $className = $this->getMetadata()->get(['action', 'types', 'set']);
                if (empty($className)) {
                    return;
                }

                /** @var Set $actionTypeService */
                $actionTypeService = $this->getInjection('container')->get($className);
                $actionTypeService->checkJob($entity);
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('status') == 'Running') {
            throw new BadRequest($this->getLanguage()->translate('jobIsRunning', 'exceptions', 'Job'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
