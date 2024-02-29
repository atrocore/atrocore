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

namespace Espo\Core;

use Atro\Core\Container;
use Atro\Core\Exceptions\Error;
use Espo\Core\Interfaces\Injectable;
use Espo\Services\Record;

class ServiceFactory
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var array
     */
    private $services = [];

    /**
     * @var array
     */
    private $classNames;

    /**
     * ServiceFactory constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->classNames = $this->getMetadata()->get(['app', 'services'], []);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function checkExists(string $name): bool
    {
        try {
            $className = $this->getClassName($name);
        } catch (Error $e) {
            $className = null;
        }

        return !empty($className);
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @throws Error
     */
    public function create(string $name)
    {
        if (!isset($this->services[$name])) {
            $className = $this->getClassName($name);

            // create service
            $service = new $className();
            if ($service instanceof Record) {
                $service->setEntityType($name);
            }

            if ($service instanceof Injectable) {
                foreach ($service->getDependencyList() as $name) {
                    $service->inject($name, $this->container->get($name));
                }
            }

            $this->services[$name] = $service;
        }

        return $this->services[$name];
    }

    /**
     * @param string $name
     *
     * @return string
     * @throws Error
     */
    protected function getClassName(string $name): string
    {
        if (!isset($this->classNames[$name])) {
            /** @var string $module */
            $module = $this->getMetadata()->get(['scopes', $name, 'module'], 'Espo');

            switch ($module) {
                case 'TreoCore':
                    $module = 'Treo';
                    break;
                case 'Custom':
                    $module = 'Espo\\Custom';
                    break;
            }

            $this->classNames[$name] = "\\$module\\Services\\$name";
        }

        if (!class_exists($this->classNames[$name])) {
            $this->classNames[$name] = "\\Atro\\Services\\$name";
        }

        if (!class_exists($this->classNames[$name])) {
            $this->classNames[$name] = "\\Espo\\Services\\$name";
        }

        if (!class_exists($this->classNames[$name])) {
            $type = $this->getMetadata()->get(['scopes', $name, 'type']);
            $this->classNames[$name] = "\\Atro\\Core\\Templates\\Services\\$type";
        }

        if (!class_exists($this->classNames[$name])) {
            throw new Error("Service '$name' was not found");
        }

        return $this->classNames[$name];
    }

    protected function getMetadata(): \Espo\Core\Utils\Metadata
    {
        return $this->container->get('metadata');
    }
}
