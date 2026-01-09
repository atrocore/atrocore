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

class ClusterItem extends Base
{
    protected $mandatorySelectAttributeList = ['entityName', 'entityId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('recordId', $entity->get('entityId'));
        $record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('recordId'));
        if (!empty($record)) {
            $entity->set('recordName', $record->get('name'));
        }
    }
}
