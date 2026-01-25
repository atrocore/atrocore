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
    protected $mandatorySelectAttributeList = ['sourceEntity', 'sourceEntityId', 'masterEntity', 'masterEntityId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('sourceId', $entity->get('sourceEntityId'));
        $sourceEntity = $this->getEntityManager()->getEntity($entity->get('sourceEntity'), $entity->get('sourceEntityId'));
        if (!empty($sourceEntity)) {
            $entity->set('sourceName', $sourceEntity->get('name'));
        }

        $entity->set('masterId', $entity->get('masterEntityId'));
        $masterEntity = $this->getEntityManager()->getEntity($entity->get('masterEntity'), $entity->get('masterEntityId'));
        if (!empty($masterEntity)) {
            $entity->set('masterName', $masterEntity->get('name'));
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
