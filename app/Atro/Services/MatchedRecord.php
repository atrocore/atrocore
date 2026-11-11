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

use Atro\Core\MatchingManager;
use Atro\Core\Templates\Services\Base;
use Atro\Repositories\Matching as MatchingRepository;
use Espo\ORM\Entity;

class MatchedRecord extends Base
{
    protected $mandatorySelectAttributeList = ['originalEntity', 'originalEntityId', 'matchedEntity', 'matchedEntityId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('originalRecordId', $entity->get('originalEntityId'));
        $originalRecord = $this->getEntityManager()->getEntity($entity->get('originalEntity'), $entity->get('originalEntityId'));
        if (!empty($originalRecord)) {
            $entity->set('originalRecordName', $originalRecord->get('name'));
        }

        $entity->set('matchedRecordId', $entity->get('matchedEntityId'));
        $matchedRecord = $this->getEntityManager()->getEntity($entity->get('matchedEntity'), $entity->get('matchedEntityId'));
        if (!empty($matchedRecord)) {
            $entity->set('matchedRecordName', $matchedRecord->get('name'));
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('matchingManager');
    }

    protected function getMatchingRepository(): MatchingRepository
    {
        return $this->getEntityManager()->getRepository('Matching');
    }

    protected function getMatchingManager(): MatchingManager
    {
        return $this->getInjection('matchingManager');
    }
}
