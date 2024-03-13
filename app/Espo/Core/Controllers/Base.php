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

namespace Espo\Core\Controllers;

use Atro\Core\Container;

abstract class Base
{
    protected $name;

    private $container;

    private $requestMethod;

    public static $defaultAction = 'index';

    public function __construct(Container $container, $requestMethod = null, $controllerName = null)
    {
        $this->container = $container;

        if (isset($requestMethod)) {
            $this->setRequestMethod($requestMethod);
        }

        if (empty($this->name)) {
            $name = $controllerName ?? get_class($this);
            if (preg_match('@\\\\([\w]+)$@', $name, $matches)) {
                $name = $matches[1];
            }
            $this->name = $name;
        }

        $this->checkControllerAccess();
    }

    protected function checkControllerAccess()
    {
        return;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    /**
     * Get request method name (Uppercase)
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return $this->requestMethod;
    }

    protected function setRequestMethod($requestMethod)
    {
        $this->requestMethod = strtoupper($requestMethod);
    }

    protected function getUser()
    {
        return $this->container->get('user');
    }

    /**
     * @return \Espo\Core\Acl
     */
    protected function getAcl()
    {
        return $this->container->get('acl');
    }

    /**
     * @return \Espo\Core\AclManager
     */
    protected function getAclManager()
    {
        return $this->container->get('aclManager');
    }

    /**
     * @return \Espo\Core\Utils\Config;
     */
    protected function getConfig()
    {
        return $this->container->get('config');
    }

    protected function getPreferences()
    {
        return $this->container->get('preferences');
    }

    /**
     * @return \Espo\Core\Utils\Metadata\;
     */
    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    /**
     * @return \Espo\Core\ServiceFactory;
     */
    protected function getServiceFactory()
    {
        return $this->container->get('serviceFactory');
    }

    protected function getService($name)
    {
        return $this->getServiceFactory()->create($name);
    }

    /**
     * @return \Espo\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getContainer()->get('entityManager');
    }

    /**
     * @return \Atro\Core\EventManager\Manager
     */
    protected function getEventManager()
    {
        return $this->getContainer()->get('eventManager');
    }
}

