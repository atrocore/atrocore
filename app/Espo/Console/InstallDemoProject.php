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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Espo\Console;

/**
 * InstallDemoProject console
 */
class InstallDemoProject extends AbstractConsole
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
        if ($this->getConfig()->get('isInstalled', false)) {
            self::show('System is already installed.', self::ERROR, true);
        }

        // fill config via environment variables
        if (isset($_SERVER['DB_NAME']) && isset($_SERVER['DB_USER']) && isset($_SERVER['DB_PASS'])) {
            $this->getConfig()->set(
                'database', [
                    'driver'   => 'pdo_mysql',
                    'host'     => !empty($_SERVER['DB_HOST']) ? $_SERVER['DB_HOST'] : 'localhost',
                    'port'     => '',
                    'charset'  => 'utf8mb4',
                    'dbname'   => $_SERVER['DB_NAME'],
                    'user'     => $_SERVER['DB_USER'],
                    'password' => $_SERVER['DB_PASS']
                ]
            );

            if (!empty($_SERVER['PASSWORD_SALT'])) {
                $this->getConfig()->set('passwordSalt', $_SERVER['PASSWORD_SALT']);
            }

            if (isset($_SERVER['CRYPT_KEY'])) {
                $this->getConfig()->set('cryptKey', $_SERVER['CRYPT_KEY'] == 'empty' ? '' : $_SERVER['CRYPT_KEY']);
            }

            if (!empty($_SERVER['LANGUAGE'])) {
                $this->getConfig()->set('language', $_SERVER['LANGUAGE']);
            }

            if (!empty($_SERVER['SITE_URL'])) {
                $this->getConfig()->set('siteUrl', 'https://' . $_SERVER['SITE_URL']);
            }

            if (!empty($_SERVER['ADMIN_USER']) && !empty($_SERVER['ADMIN_PASS'])) {
                $this->getConfig()->set(
                    'demo', [
                        'username' => $_SERVER['ADMIN_USER'],
                        'password' => $_SERVER['ADMIN_PASS'],
                    ]
                );
            }
            $this->getConfig()->save();
        }

        $this->getContainer()->get('serviceFactory')->create('Installer')->createAdmin(
            [
                'username'        => $this->getConfig()->get('demo.username'),
                'password'        => $this->getConfig()->get('demo.password'),
                'confirmPassword' => $this->getConfig()->get('demo.password'),
            ]
        );

        self::show('Demo project installed successfully.', self::SUCCESS, true);
    }
}
