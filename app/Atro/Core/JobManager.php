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

namespace Atro\Core;

use Espo\ORM\Entity;

class JobManager
{
    public function executeJob(Entity $job): bool
    {
        $i = 0;
        while ($i < 60) {
            $i++;
            sleep(1);
        }

        return true;
    }
}
