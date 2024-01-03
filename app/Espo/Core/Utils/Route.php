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

namespace Espo\Core\Utils;

use Espo\Core\DataManager;
use Atro\Core\ModuleManager\Manager as ModuleManager;

/**
 * Class Route
 */
class Route
{
    private const CUSTOM_PATH = 'custom/Espo/Custom/Resources/routes.json';

    /**
     * @var File\Manager
     */
    private $fileManager;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var DataManager
     */
    private $dataManager;

    /**
     * @var null|array
     */
    protected $data = null;

    /**
     * Route constructor.
     *
     * @param File\Manager  $fileManager
     * @param ModuleManager $moduleManager
     * @param DataManager   $dataManager
     */
    public function __construct(File\Manager $fileManager, ModuleManager $moduleManager, DataManager $dataManager)
    {
        $this->fileManager = $fileManager;
        $this->moduleManager = $moduleManager;
        $this->dataManager = $dataManager;
    }

    /**
     * @param string $key
     * @param array  $returns
     *
     * @return array
     */
    public function get($key = '', $returns = null)
    {
        if (!isset($this->data)) {
            $this->data = $this->dataManager->getCacheData('route');
            if ($this->data === null) {
                $this->data = $this->unify();
                $this->dataManager->setCacheData('route', $this->data);
            }
        }

        if (empty($key)) {
            return $this->data;
        }

        $keys = explode('.', $key);

        $lastRoute = $this->data;
        foreach ($keys as $keyName) {
            if (isset($lastRoute[$keyName]) && is_array($lastRoute)) {
                $lastRoute = $lastRoute[$keyName];
            } else {
                return $returns;
            }
        }

        return $lastRoute;
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->get();
    }

    /**
     * @param array  $currData
     * @param string $routeFile
     *
     * @return array
     */
    public function getAddData($currData, $routeFile)
    {
        if (file_exists($routeFile)) {
            $content = $this->fileManager->getContents($routeFile);
            $arrayContent = Json::getArrayData($content);
            if (empty($arrayContent)) {
                $GLOBALS['log']->error('Route::unify() - Empty file or syntax error - [' . $routeFile . ']');
                return $currData;
            }

            $currData = $this->addToData($currData, $arrayContent);
        }

        return $currData;
    }

    /**
     * Unify routes
     *
     * @return array
     */
    protected function unify()
    {
        // for custom
        $data = $this->getAddData([], self::CUSTOM_PATH);

        // for modules
        foreach ($this->moduleManager->getModules() as $module) {
            $module->loadRoutes($data);
        }

        // for core
        $data = $this->getAddData($data, CORE_PATH . '/Atro/Resources/routes.json');

        return $data;
    }

    /**
     * @param array $data
     * @param array $newData
     *
     * @return array
     */
    protected function addToData($data, $newData)
    {
        if (!is_array($newData)) {
            return $data;
        }

        foreach ($newData as $route) {
            $route['route'] = $this->adjustPath($route['route']);
            $data[] = $route;
        }

        return $data;
    }

    /**
     * Check and adjust the route path
     *
     * @param string $routePath - it can be "/App/user",  "App/user"
     *
     * @return string - "/App/user"
     */
    protected function adjustPath($routePath)
    {
        $routePath = trim($routePath);

        if (substr($routePath, 0, 1) != '/') {
            return '/' . $routePath;
        }

        return $routePath;
    }
}