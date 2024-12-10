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

use Espo\ORM\Entity;

class ClearEntity extends AbstractJob implements JobInterface
{
    public function run(Entity $job): void
    {
        $entityName = $job->get('payload')['entityName'] ?? null;
        if (empty($entityName)) {
            return;
        }

        try {
            $this->getEntityManager()->getRepository($entityName)->clearDeletedRecords();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("Clear Entity failed for $entityName: {$e->getMessage()}");
        }
    }
}
