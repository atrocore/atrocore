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

namespace Treo\Core\ModuleManager;

use Espo\Core\Exceptions\Error;
use Espo\Core\Container;
use Treo\Services\Composer;

/**
 * Class Manager
 */
class Manager
{
    /**
     * @var array|null
     */
    private $modules = null;

    /**
     * @var Container
     */
    private $container;

    /**
     * Prepare version
     *
     * @param string $version
     *
     * @return string
     */
    public static function prepareVersion(string $version): string
    {
        return str_replace('v', '', $version);
    }

    /**
     * Manager constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get modules
     *
     * @param bool $reload ;
     *
     * @return array
     * @throws Error
     * @throws \ReflectionException
     */
    public function getModules(bool $reload = false): array
    {
        if ($reload) {
            $this->modules = null;
        }

        if (is_null($this->modules)) {
            $this->modules = [];

            // prepare path
            $path = 'data/modules.json';

            // parse data
            $data = [];
            if (file_exists($path)) {
                $data = array_merge($data, json_decode(file_get_contents($path), true));
            }

            // load modules
            if (!empty($data)) {
                foreach ($data as $module) {
                    // prepare class name
                    $className = "\\$module\\Module";
                    if (property_exists($className, 'isTreoModule')) {
                        // prepare base path
                        $path = (new \ReflectionClass($className))->getFileName();

                        // get module path
                        $modulePath = '';
                        while (empty($modulePath)) {
                            $path = dirname($path);
                            if (file_exists($path . "/composer.json")) {
                                $modulePath = $path . "/";
                            }

                            if ($path == '/') {
                                throw new Error('Error at modules loader');
                            }
                        }

                        $this->modules[$module] = new $className(
                            $module,
                            $modulePath,
                            $this->getPackage($module),
                            $this->container
                        );
                    }
                }
            }
        }

        return $this->modules;
    }

    /**
     * Get module
     *
     * @param string $id
     *
     * @return AbstractModule|null
     */
    public function getModule(string $id): ?AbstractModule
    {
        foreach ($this->getModules() as $name => $module) {
            if ($name == $id) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Get composer package
     *
     * @param string $id
     *
     * @return array
     */
    private function getPackage(string $id): array
    {
        if (file_exists(Composer::$composerLock)) {
            $data = json_decode(file_get_contents(Composer::$composerLock), true);
            if (!empty($data['packages'])) {
                foreach ($data['packages'] as $package) {
                    if (!empty($package['extra']['treoId']) && $package['extra']['treoId'] == $id) {
                        return $package;
                    }
                }
            }
        }

        return [];
    }
}
