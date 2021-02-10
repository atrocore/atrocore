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

namespace Treo\Core\Loaders;

use Espo\Core\Utils\Config;

/**
 * Class Pdo
 */
class Pdo extends Base
{
    public const DRIVER_PLATFORM_MAP = ['pdo_mysql' => 'Mysql', 'mysqli' => 'Mysql'];

    /**
     * @inheritdoc
     */
    public function load()
    {
        /** @var Config $config */
        $config = $this->getContainer()->get('config');

        $params = [
            'host'      => $config->get('database.host'),
            'port'      => $config->get('database.port'),
            'dbname'    => $config->get('database.dbname'),
            'user'      => $config->get('database.user'),
            'charset'   => $config->get('database.charset', 'utf8'),
            'password'  => $config->get('database.password'),
            'driver'    => $config->get('database.driver'),
            'platform'  => $config->get('database.platform'),
            'sslCA'     => $config->get('database.sslCA'),
            'sslCert'   => $config->get('database.sslCert'),
            'sslKey'    => $config->get('database.sslKey'),
            'sslCAPath' => $config->get('database.sslCAPath'),
            'sslCipher' => $config->get('database.sslCipher')
        ];

        // prepare platform
        $platform = strtolower(self::DRIVER_PLATFORM_MAP[$params['driver']]);

        // prepare params
        $port = empty($params['port']) ? '' : "port={$params['port']};";

        $options = [];
        if (isset($params['sslCA'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = $params['sslCA'];
        }
        if (isset($params['sslCert'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CERT] = $params['sslCert'];
        }
        if (isset($params['sslKey'])) {
            $options[\PDO::MYSQL_ATTR_SSL_KEY] = $params['sslKey'];
        }
        if (isset($params['sslCAPath'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CAPATH] = $params['sslCAPath'];
        }
        if (isset($params['sslCipher'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CIPHER] = $params['sslCipher'];
        }

        $pdo = new \PDO("{$platform}:host={$params['host']};{$port}dbname={$params['dbname']};charset={$params['charset']}", $params['user'], $params['password'], $options);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
