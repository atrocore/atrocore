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

class FindMatchesForRecord extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $matchingId = $job->getPayload()['matchingId'] ?? null;
        $entityName = $job->getPayload()['entityName'] ?? null;
        $entityId = $job->getPayload()['entityId'] ?? null;

        if (empty($entityName) || empty($entityId)) {
            return;
        }

        $entity = $this->getEntityManager()->getEntity($entityName, $entityId);
        if (!$entity) {
            return;
        }

        $matching = $this->getEntityManager()->getEntity('Matching', $matchingId);
        if (!$matching) {
            return;
        }

        $this->getContainer()->get('matchingManager')->findMatches($matching, $entity);
    }
}
