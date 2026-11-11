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

namespace Atro\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Atro\Core\Utils\Config;
use Espo\ORM\EntityManager;

/**
 * Creates the scheduled jobs of the system. Sending the anonymous error reports is active when it is enabled
 * in the config.
 */
class CreateScheduledJobs implements AfterInstallInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    public static function getSortOrder(): int
    {
        return 20;
    }

    public function run(): void
    {
        $jobs = [
            [
                'name'       => 'Update system automatically',
                'type'       => 'ComposerAutoUpdate',
                'isActive'   => false,
                'scheduling' => '0 0 * * SUN',
            ],
            [
                'name'       => 'Update currency exchange via ECB',
                'type'       => 'UpdateCurrencyExchangeViaECB',
                'isActive'   => true,
                'scheduling' => '0 2 * * *',
            ],
            [
                'name'       => 'Clear deleted data',
                'type'       => 'ClearEntities',
                'isActive'   => true,
                'scheduling' => '0 2 1 * *',
            ],
            [
                'name'       => 'Check system updates',
                'type'       => 'CheckUpdates',
                'isActive'   => true,
                'scheduling' => '0 2 * * *',
            ],
            [
                'name'       => 'Calculate script fields',
                'type'       => 'CalculateScriptFieldsForEntities',
                'isActive'   => true,
                'scheduling' => '0 3 * * *',
            ],
            [
                'name'       => 'Find matches',
                'type'       => 'FindMatches',
                'isActive'   => true,
                'scheduling' => '0 */2 * * *',
            ],
            [
                'name'       => 'Send anonymous error reports to AtroCore',
                'type'       => 'SendReports',
                'isActive'   => !empty($this->config->get('reportingEnabled')),
                'scheduling' => '*/15 * * * *',
            ],
        ];

        foreach ($jobs as $data) {
            $job = $this->entityManager->getRepository('ScheduledJob')->get();
            $job->set($data);

            $this->entityManager->saveEntity($job);
        }
    }
}
