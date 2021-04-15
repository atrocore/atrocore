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

use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Treo\Core\Application;
use Treo\Core\ORM\EntityManager;
use Treo\Services\Composer;

/**
 * Class Daemon
 */
class Daemon extends AbstractConsole
{
    /**
     * @var bool
     */
    public static $isHidden = true;

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function run(array $data): void
    {
        $method = $data['name'] . 'Daemon';
        if (method_exists($this, $method)) {
            $this->$method($data['id']);
        }
    }

    /**
     * @param string $id
     */
    protected function composerDaemon(string $id): void
    {
        /** @var string $log */
        $log = Application::COMPOSER_LOG_FILE;

        while (true) {
            // delete check-up file
            if (file_exists(Composer::CHECK_UP_FILE)) {
                unlink(Composer::CHECK_UP_FILE);
            }

            if (file_exists(Cron::DAEMON_KILLER)) {
                break;
            }

            if (file_exists($log)) {
                /** @var EntityManager $em */
                $em = $this->getContainer()->get('entityManager');

                /** @var User $user */
                $user = $em
                    ->getRepository('User')
                    ->select(['id'])
                    ->where(['id' => file_get_contents($log)])
                    ->findOne();

                // skip if no such user
                if (empty($user)) {
                    // remove log file
                    unlink($log);
                    continue 1;
                }

                // cleanup
                file_put_contents($log, '');

                exec($this->getPhp() . " composer.phar self-update 2>/dev/null", $output, $exitCode);
                if (empty($exitCode)) {
                    exec($this->getPhp() . " composer.phar update >> $log 2>&1", $output, $exitCode);
                } else {
                    file_put_contents($log, "Failed! The new version of the composer can't be copied.");
                }

                try {
                    // create note
                    $note = $em->getEntity('Note');
                    $note->set('type', 'composerUpdate');
                    $note->set('parentType', 'ModuleManager');
                    $note->set('data', ['status' => ($exitCode == 0) ? 0 : 1, 'output' => file_get_contents($log)]);
                    $note->set('createdById', $user->get('id'));
                    $em->saveEntity($note, ['skipAll' => true]);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error('Creating composer update log failed: ' . $e->getMessage());
                }

                // remove log file
                unlink($log);
            }

            sleep(1);
        }
    }

    /**
     * @param string $id
     */
    protected function qmDaemon(string $id): void
    {
        /** @var string $stream */
        $stream = explode('-', $id)[0];

        while (true) {
            if (file_exists(Cron::DAEMON_KILLER)) {
                break;
            }

            if (file_exists(\Espo\Core\QueueManager::FILE_PATH)) {
                exec($this->getPhp() . " index.php qm $stream --run");
            }

            sleep(1);
        }
    }

    /**
     * @param string $id
     */
    protected function notificationDaemon(string $id): void
    {
        while (true) {
            if (file_exists(Cron::DAEMON_KILLER)) {
                break;
            }

            if (!empty(Util::scanDir(\Espo\Repositories\Notification::UPDATE_COUNT_PATH))) {
                exec($this->getPhp() . " index.php notifications --refresh");
            }

            sleep(1);
        }
    }

    /**
     * @return string
     */
    protected function getPhp(): string
    {
        return (new \Espo\Core\Utils\System())->getPhpBin();
    }
}