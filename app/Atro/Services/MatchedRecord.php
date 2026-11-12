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
    protected $mandatorySelectAttributeList = ['matchSubjectEntity', 'matchSubjectEntityId', 'matchCandidateEntity', 'matchCandidateEntityId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('matchSubjectId', $entity->get('matchSubjectEntityId'));
        $matchSubject = $this->getEntityManager()->getEntity($entity->get('matchSubjectEntity'), $entity->get('matchSubjectEntityId'));
        if (!empty($matchSubject)) {
            $entity->set('matchSubjectName', $matchSubject->get('name'));
        }

        $entity->set('matchCandidateId', $entity->get('matchCandidateEntityId'));
        $matchCandidate = $this->getEntityManager()->getEntity($entity->get('matchCandidateEntity'), $entity->get('matchCandidateEntityId'));
        if (!empty($matchCandidate)) {
            $entity->set('matchCandidateName', $matchCandidate->get('name'));
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
