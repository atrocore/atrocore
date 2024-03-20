<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Espo\Jobs;

use Atro\Console\AbstractConsole;
use Espo\Core\DataManager;
use Espo\Core\Jobs\Base;

class CheckUpdates extends Base
{
    public const CHECK_UPDATES_LOG_FILE = 'data/check-updates.log';

    public function run(): void
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
