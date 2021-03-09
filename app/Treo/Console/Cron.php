<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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

namespace Treo\Console;

/**
 * Cron console
 */
class Cron extends AbstractConsole
{
    const DAEMON_KILLER = 'data/process-kill.txt';

    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Run CRON.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled'))) {
            exit(1);
        }

        // kill daemon killer
        if (file_exists(self::DAEMON_KILLER)) {
            unlink(self::DAEMON_KILLER);
        }

        // get active processes
        exec('ps ax | grep index.php', $processes);
        $processes = implode(' | ', $processes);

        /** @var string $php */
        $php = (new \Espo\Core\Utils\System())->getPhpBin();

        /** @var string $id */
        $id = $this->getConfig()->get('appId');

        // open daemon for composer
        if (empty(strpos($processes, "index.php daemon composer $id"))) {
            exec("$php index.php daemon composer $id >/dev/null 2>&1 &");
        }

        // open daemon queue manager stream 0
        if (empty(strpos($processes, "index.php daemon qm 0-$id"))) {
            exec("$php index.php daemon qm 0-$id >/dev/null 2>&1 &");
        }

        // open daemon queue manager stream 1
        if (empty(strpos($processes, "index.php daemon qm 1-$id"))) {
            exec("$php index.php daemon qm 1-$id >/dev/null 2>&1 &");
        }

        // open daemon notification
        if (empty(strpos($processes, "index.php daemon notification $id"))) {
            exec("$php index.php daemon notification $id >/dev/null 2>&1 &");
        }

        // run cron jobs
        $this->runCronManager();
    }

    /**
     * Run cron manager
     */
    protected function runCronManager(): void
    {
        $auth = new \Treo\Core\Utils\Auth($this->getContainer());
        $auth->useNoAuth();

        $this->getContainer()->get('cronManager')->run();
    }
}
