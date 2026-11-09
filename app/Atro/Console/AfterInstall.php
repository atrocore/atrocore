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

namespace Atro\Console;

use Atro\Core\ModuleManager\ModuleLifecycle;

/**
 * Runs the AfterInstall steps of the core and of every module. The steps are run once, as the last step of the
 * installation of the system: the command does not check whether they were run before.
 */
class AfterInstall extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Run the post-install steps of the core and of every module. Run once, when the system is installed.';
    }

    public function run(array $data): void
    {
        try {
            $this->getContainer()->get(ModuleLifecycle::class)->afterInstallAll();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Post-install steps failed: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            self::show('The post-install steps failed: ' . $e->getMessage(), self::ERROR, true);
        }

        self::show('The post-install steps are done.', self::SUCCESS);
    }
}
