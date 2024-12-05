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

use Atro\Console\AbstractConsole;
use Espo\Core\DataManager;
use Espo\ORM\Entity;

class CheckUpdates extends AbstractJob implements JobInterface
{
    public const CHECK_UPDATES_LOG_FILE = 'data/check-updates.log';

    public function run(Entity $job): void
    {
        $php = AbstractConsole::getPhpBinPath($this->getConfig());
        $log = self::CHECK_UPDATES_LOG_FILE;

        if (file_exists($log)) {
            unlink($log);
        }

        exec("$php composer.phar update --dry-run >> $log 2>&1");

        DataManager::pushPublicData('isNeedToUpdate', self::isUpdatesAvailable());
    }

    public static function isUpdatesAvailable(): bool
    {
        if (!file_exists(self::CHECK_UPDATES_LOG_FILE)) {
            return false;
        }

        return strpos(file_get_contents(self::CHECK_UPDATES_LOG_FILE), 'Package operations:') !== false;
    }
}
