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
    private Container $container;

    public const QUEUE_FILE = 'data/job-queue.log';

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function executeJob(Entity $job): bool
    {
        // auth as system
        $auth = new \Espo\Core\Utils\Auth($this->container);
        $auth->useNoAuth();

        $i = 0;
        while ($i < 60) {
            $i++;
            sleep(1);
        }

        return true;
    }
}
