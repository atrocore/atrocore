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

        if ($entity->isNew() && $entity->get('type') === 'SendReports') {
            $exists = $this->where(['type' => 'SendReports'])->findOne();
            if (!empty($exists)) {
                throw new BadRequest($this->getInjection('language')->translate('onlyOneSendReportsJobAllowed', 'exceptions', 'ScheduledJob'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('isActive') && $entity->get('type') === 'SendReports') {
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
