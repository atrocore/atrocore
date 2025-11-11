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
    protected $mandatorySelectAttributeList = ['stagingEntity', 'stagingEntityId', 'masterEntity', 'masterEntityId'];

    public function getMatchedRecords(string $code, string $entityName, string $entityId, array $statuses): array
    {
        if (!$this->getAcl()->check($entityName, 'read')) {
            return [];
        }

        $entity = $this->getEntityManager()->getRepository($entityName)->get($entityId);
        if (empty($entity)) {
            return [];
        }

        if (!$this->getAcl()->check($entity, 'read')) {
            return [];
        }

        $matching = $this->getEntityManager()->getRepository('Matching')->getEntityByCode($code);
        if (empty($matching)) {
            return [];
        }

        if (empty($statuses)) {
            $statuses = ["found", "confirmed"];
        }

        if ($entityName === $matching->get('stagingEntity')) {
            if (!$this->getMatchingRepository()->isMatchingSearchedForStaging($matching, $entity)) {
                $this->getMatchingManager()->findMatches($matching, $entity);
            }

            return $this->getRepository()->getMatchedRecords($matching, $entity, $statuses);
        }

        if ($entityName === $matching->get('masterEntity')) {
            return $this->getRepository()->getForeignMatchedRecords($matching, $entity, $statuses);
        }

        return [];
    }

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
