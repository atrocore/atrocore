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

use Atro\Core\JobManager;
use Espo\ORM\EntityManager;

class Job extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Execute job.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled')) || empty($data['id'])) {
            exit(1);
        }

        $parts = explode('_', $data['id']);
        $id = array_pop($parts);

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get('entityManager');

        $job = $entityManager->getEntity('Job', $id);
        if (empty($job)) {
            self::show('No such job!', self::ERROR, true);
        }

        $result = $this->getContainer()->get(JobManager::class)->executeJob($job);

        if ($result) {
            self::show('Job has been executed successfully!', self::SUCCESS, true);
        } else {
            self::show('Job has not been executed because of errors!', self::ERROR, true);
        }
    }
}
