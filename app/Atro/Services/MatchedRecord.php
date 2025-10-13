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
use Espo\ORM\Entity;

class MatchedRecord extends Base
{
    protected $mandatorySelectAttributeList = ['stagingEntity', 'stagingEntityId', 'masterEntity', 'masterEntityId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('stagingId', $entity->get('stagingEntityId'));
        $stagingEntity = $this->getEntityManager()->getEntity($entity->get('stagingEntity'), $entity->get('stagingEntityId'));
        if (!empty($stagingEntity)) {
            $entity->set('stagingName', $stagingEntity->get('name'));
        }

        $entity->set('masterId', $entity->get('masterEntityId'));
        $masterEntity = $this->getEntityManager()->getEntity($entity->get('masterEntity'), $entity->get('masterEntityId'));
        if (!empty($masterEntity)) {
            $entity->set('masterName', $masterEntity->get('name'));
        }
    }
}
