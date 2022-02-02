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

use Espo\ORM\EntityManager;
use Treo\Core\Application;

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
        $php = $this->getPhpBin();

        /** @var string $id */
        $id = $this->getConfig()->get('appId');

        // open daemon for composer
        if (empty(strpos($processes, "index.php daemon composer $id"))) {
            if ($this->isComposerDaemonBlocked()) {
                return;
            }

            exec("$php index.php daemon composer $id >/dev/null 2>&1 &");
        }

        // exit if system is updating now
        if (Application::isSystemUpdating()) {
            return;
        }

        // open daemon queue manager streams
        $i = 0;
        while ($i < $this->getConfig()->get('queueManagerWorkersCount', 4)) {
            if (empty(strpos($processes, "index.php daemon qm $i-$id"))) {
                exec("$php index.php daemon qm $i-$id >/dev/null 2>&1 &");
            }
            $i++;
        }

        // open daemon notification
        if (empty(strpos($processes, "index.php daemon notification $id"))) {
            exec("$php index.php daemon notification $id >/dev/null 2>&1 &");
        }

        // open daemon for pseudo transaction manager
        if (empty(strpos($processes, "index.php daemon pt $id"))) {
            exec("$php index.php daemon pt $id >/dev/null 2>&1 &");
        }

        // check auth tokens
        $this->authTokenControl();

        // run cron jobs
        $this->runCronManager();
    }

    /**
     * Run cron manager
     */
    protected function runCronManager(): void
    {
        $auth = new \Espo\Core\Utils\Auth($this->getContainer());
        $auth->useNoAuth();

        $this->getContainer()->get('cronManager')->run();
    }

    /**
     * @return bool
     */
    private function isComposerDaemonBlocked(): bool
    {
        if (!file_exists(Application::COMPOSER_LOG_FILE)) {
            return false;
        }

        $log = file_get_contents(Application::COMPOSER_LOG_FILE);

        if (strpos($log, 'Creating restore point') === false) {
            return false;
        }

        // @todo remove this after 01.06.2021
        if (strpos($log, 'Sending notification(s)') !== false) {
            unlink(Application::COMPOSER_LOG_FILE);
        }

        return true;
    }

    private function authTokenControl(): void
    {
        if ($this->getConfig()->get('authenticationMethod') != 'Token') {
            return;
        }

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        $tokenList = $em
            ->getRepository('AuthToken')
            ->select(['id', 'lifetime', 'idleTime', 'createdAt', 'lastAccess'])
            ->where(['isActive' => true])
            ->find();

        foreach ($tokenList as $token) {
            $authTokenLifetime = $token->get('lifetime') !== null ? $token->get('lifetime') : $this->getConfig()->get('authTokenLifetime');
            if ($authTokenLifetime && new \DateTime($token->get('createdAt')) < (new \DateTime())->modify('-' . $authTokenLifetime . ' hours')) {
                $token->set('isActive', false);
                $em->saveEntity($token);
                continue 1;
            }

            $authTokenMaxIdleTime = $token->get('idleTime') !== null ? $token->get('idleTime') : $this->getConfig()->get('authTokenMaxIdleTime');
            if ($authTokenMaxIdleTime && new \DateTime($token->get('lastAccess')) < (new \DateTime())->modify('-' . $authTokenMaxIdleTime . ' hours')) {
                $token->set('isActive', false);
                $em->saveEntity($token);
            }
        }
    }
}
