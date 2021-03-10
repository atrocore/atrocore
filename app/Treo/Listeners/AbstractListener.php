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

namespace Treo\Listeners;

use Espo\Core\Container;
use Espo\Core\Exceptions\Error;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Config;

/**
 * AbstractListener class
 */
abstract class AbstractListener
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var array
     */
    protected $services = [];

    /**
     * Set container
     *
     * @param Container $container
     *
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Get container
     *
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get service
     *
     * @param string $name
     *
     * @return object
     */
    protected function getService(string $name)
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this
                ->getContainer()
                ->get('serviceFactory')
                ->create($name);
        }

        return $this->services[$name];
    }

    /**
     * Get entity manager
     *
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    /**
     * Get config
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    /**
     * Get language
     *
     * @return Language
     * @throws Error
     */
    protected function getLanguage(): Language
    {
        if (is_null($this->getContainer()->get('preferences'))) {
            throw new Error('No preferences defined');
        }

        return $this->getContainer()->get('language');
    }

    /**
     * Get metadata
     *
     * @return \Treo\Core\Utils\Metadata
     */
    protected function getMetadata(): \Treo\Core\Utils\Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
