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

namespace Espo\Core\Factories;

use Atro\Core\Container;
use Atro\Core\Factories\FactoryInterface as Factory;

class EntityManager implements Factory
{
    public function create(Container $container)
    {
        // get config
        $config = $container->get('config');

        $params = [
            'host'                       => $config->get('database.host'),
            'port'                       => $config->get('database.port'),
            'dbname'                     => $config->get('database.dbname'),
            'user'                       => $config->get('database.user'),
            'charset'                    => $config->get('database.charset', 'utf8'),
            'password'                   => $config->get('database.password'),
            'metadata'                   => $container->get('ormMetadata')->getData(),
            'repositoryFactoryClassName' => \Espo\Core\ORM\RepositoryFactory::class,
            'driver'                     => $config->get('database.driver'),
            'platform'                   => $config->get('database.platform'),
            'sslCA'                      => $config->get('database.sslCA'),
            'sslCert'                    => $config->get('database.sslCert'),
            'sslKey'                     => $config->get('database.sslKey'),
            'sslCAPath'                  => $config->get('database.sslCAPath'),
            'sslCipher'                  => $config->get('database.sslCipher'),
            'container'                  => $container
        ];

        return new \Espo\Core\ORM\EntityManager($params);
    }
}
