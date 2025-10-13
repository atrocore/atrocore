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

declare(strict_types=1);

namespace Atro\Services;

use Atro\Core\MatchingManager;
use Atro\Core\Templates\Services\ReferenceData;
use Atro\Repositories\Matching as MatchingRepository;

class Matching extends ReferenceData
{
    public function getMatchedRecords(string $code, string $entityName, string $entityId, array $statuses): array
    {
        $matching = $this->getEntityManager()->getRepository('Matching')->getEntityByCode($code);
        if (empty($matching)) {
            return [];
        }

        $entity = $this->getEntityManager()->getRepository($entityName)->get($entityId);
        if (empty($entity)) {
            return [];
        }

        if (empty($statuses)) {
            $statuses = ["found", "confirmed"];
        }

        if ($entityName === $matching->get('stagingEntity')) {
            if (!$this->getRepository()->isMatchingSearchedForStaging($matching, $entity)) {
                $this->getMatchingManager()->findMatches($matching, $entity);
            }
            return $this->getRepository()->getMatchedRecords($matching, $entity, $statuses);
        }

        if ($entityName === $matching->get('masterEntity')) {
            return $this->getRepository()->getForeignMatchedRecords($matching, $entity, $statuses);
        }

        return [];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('matchingManager');
    }

    protected function getRepository(): MatchingRepository
    {
        return parent::getRepository();
    }

    protected function getMatchingManager(): MatchingManager
    {
        return $this->getInjection('matchingManager');
    }
}
