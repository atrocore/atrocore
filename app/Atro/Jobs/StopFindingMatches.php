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

class StopFindingMatches extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $matchingData = $job->getPayload()['matching'] ?? [];
        $entityName = $job->getPayload()['entityName'] ?? null;

        if (empty($entityName) || empty($matchingData['matchedRecordsMax']) || $matchingData['matchedRecordsMax'] < 1) {
            return;
        }

        if (empty($this->getConfig()->get("matchings.{$matchingData['id']}"))) {
            return;
        }

        $deactivate = $this
            ->getEntityManager()
            ->getRepository('MatchedRecord')
            ->checkMatchedRecordsMax($matchingData['id'], $matchingData['matchedRecordsMax']);

        if ($deactivate) {
            $this
                ->getEntityManager()
                ->getRepository('Matching')
                ->deactivate($matchingData['id']);
        }
    }
}
