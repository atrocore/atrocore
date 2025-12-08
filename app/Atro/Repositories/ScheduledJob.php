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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ScheduledJob extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('actionId'))) {
            $action = $this->getEntityManager()->getEntity('Action', $entity->get('actionId'));
            if (!empty($action) && !empty($this->getMetadata()->get("action.typesData.{$action->get('type')}.forEditModeOnly"))) {
                throw new BadRequest(
                    sprintf(
                        $this->getInjection('language')->translate('forEditModeOnly', 'exceptions', 'Action'),
                        $action->get('name')
                    )
                );
            }
        }

        $this->validateByType($entity);

        parent::beforeSave($entity, $options);
    }

    protected function beforeRestore($id)
    {
        parent::beforeRestore($id);

        $entity = $this
            ->where(['id' => $id])
            ->findOne(['withDeleted' => true]);

        if (!empty($entity)) {
            $this->validateByType($entity);
        }
    }

    protected function validateByType(Entity $entity): void
    {
        // validation for unique job types
        if ($this->getMetadata()->get("app.jobTypes.{$entity->get('type')}.unique")) {
            $where = ['type' => $entity->get('type')];
            if (!$entity->isNew()) {
                $where['id!='] = $entity->id;
            }

            $exists = $this->where($where)->findOne();
            if (!empty($exists)) {
                $message = $this->getInjection('language')->translate('onlyOneJobTypeAllowed', 'exceptions', 'ScheduledJob');
                $typeLabel = $this->getInjection('language')->translateOption($entity->get('type'), 'type', 'ScheduledJob');

                throw new BadRequest(sprintf($message, $typeLabel));
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('isActive') && $entity->get('type') === 'SendReports') {
            $this->getConfig()->set('reportingEnabled', $entity->get('isActive'));
            $this->getConfig()->save();
        }
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        if ($entity->get('type') === 'SendReports') {
            $this->getConfig()->set('reportingEnabled', $entity->get('isActive'));
            $this->getConfig()->save();
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        if ($entity->get('type') === 'SendReports') {
            $this->getConfig()->remove('reportingEnabled');
            $this->getConfig()->save();
        }
    }
}
