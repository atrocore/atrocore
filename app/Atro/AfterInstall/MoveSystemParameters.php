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

/**
 * Moves the system parameters the installer has written to data/config.php to data/config.system.php.
 */
class MoveSystemParameters implements AfterInstallInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public static function getSortOrder(): int
    {
        return 100;
    }

    public function run(): void
    {
        $this->config->moveSystemParameters();
    }
}
