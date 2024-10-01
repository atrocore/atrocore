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

namespace Atro\Console;

use Espo\ORM\EntityManager;

class ScheduledJob extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Run Scheduled Job.';
    }

    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled'))) {
            exit(1);
        }

        $auth = new \Espo\Core\Utils\Auth($this->getContainer());
        $auth->useNoAuth();

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        $scheduledJob = $em->getEntity('ScheduledJob', $data['id']);
        if (empty($scheduledJob) || $scheduledJob->get('status') !== 'Active') {
            self::show('Scheduled job has been deleted or deactivated.', self::ERROR, true);
        }

        $className = $this->getContainer()->get('scheduledJob')->get($scheduledJob->get('job'));
        if ($className === false) {
            self::show('Wrong scheduled Job.', self::ERROR, true);
        }

        (new $className($this->getContainer()))->run(null, null, null, $scheduledJob->get('id'));
    }
}
