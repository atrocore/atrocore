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

namespace Atro\Jobs;

use Atro\Entities\Job;
use Atro\Repositories\Matching;

class FindMatchesForRecord extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $matchingData = $job->getPayload()['matching'] ?? [];
        $entityName = $job->getPayload()['entityName'] ?? null;
        $entityId = $job->getPayload()['entityId'] ?? null;

        if (empty($entityName) || empty($entityId) || empty($matchingData['code'])) {
            return;
        }

        if (empty($this->getConfig()->get("matchings.{$matchingData['code']}"))) {
            return;
        }

        $entity = $this->getEntityManager()->getEntity($entityName, $entityId);
        if (!$entity) {
            return;
        }

        if (!empty($entity->get(Matching::prepareFieldName($matchingData['code'])))) {
            return;
        }

        $matchingRules = $this->getEntityManager()->createCollection('MatchingRule', []);
        foreach ($matchingData['rules'] ?? [] as $ruleData) {
            $matchingRule = $this->getEntityManager()->getEntity('MatchingRule');
            $matchingRule->set($ruleData);
            $matchingRules->append($matchingRule);
        }

        $matching = $this->getEntityManager()->getEntity('Matching');
        $matching->set($matchingData);
        $matching->set('matchingRules', $matchingRules);

        $this->getContainer()->get('matchingManager')->findMatches($matching, $entity);
    }
}
