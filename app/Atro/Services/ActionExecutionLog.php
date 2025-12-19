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

use Atro\Core\Templates\Services\Archive;
use Espo\ORM\Entity;

class ActionExecutionLog extends Archive
{
    protected $mandatorySelectAttributeList = ['entityName', 'entityId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!empty($entity->get('entityId'))) {
            $entity->set('entityRecordId', $entity->get('entityId'));
            $entity->set('entityRecordName', $entity->get('entityId'));

            $record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('entityId'));
            if (!empty($record)) {
                $entity->set('entityRecordName', $record->get('name'));
            }
        }
    }
}
