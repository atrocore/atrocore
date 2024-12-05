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

class CleanupEntity extends AbstractJob implements JobInterface
{
    public function run(Entity $job): void
    {
        $i = 0;
        while ($i < 10) {
            $i++;
            sleep(1);
        }
    }
}
