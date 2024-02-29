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

use Espo\Core\EventManager\Event;
use Atro\Core\Exceptions\NotFound;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Slim\Http\Request;

class ControllerManager extends Injectable
{
    public function __construct()
    {
        $this->addDependency('container');
    }

    public static function getControllerClassName(string $controllerName, Metadata $metadata): string
    {
        $className = Util::normilizeClassName($controllerName);

        // for custom
        $controllerClassName = "\\Espo\\Custom\\Controllers\\$className";

        // for modules
        if (!class_exists($controllerClassName)) {
            $moduleName = $metadata->getScopeModuleName($controllerName);
            if (!empty($moduleName)) {
                $controllerClassName = "\\$moduleName\\Controllers\\$className";
            }
        }

        if (!class_exists($controllerClassName)) {
            $controllerClassName = "\\Atro\\Controllers\\$className";
        }

        if (!class_exists($controllerClassName)) {
            $controllerClassName = "\\Espo\\Controllers\\$className";
        }

        if (!class_exists($controllerClassName)) {
            $type = $metadata->get(['scopes', $controllerName, 'type']);
            $controllerClassName = "\\Atro\\Core\\Templates\\Controllers\\$type";
        }

        if (!class_exists($controllerClassName)) {
            return '';
        }

        return $controllerClassName;
    }

    /**
     * Precess
     *
     * @param string      $controllerName
     * @param string      $actionName
     * @param array       $params
     * @param mixed       $data
     * @param Request     $request
     * @param object|null $response
     *
     * @return string
     * @throws NotFound
     */
    public function process($controllerName, $actionName, $params, $data, $request, $response = null)
    {
        $controllerClassName = self::getControllerClassName($controllerName, $this->getMetadata());

        if (empty($controllerClassName) || !class_exists($controllerClassName)) {
            throw new NotFound("Controller '$controllerName' is not found");
        }

        if (empty($actionName)) {
            throw new NotFound("Action '$actionName' for controller '$controllerName' is not found");
        }

        if ($data && stristr($request->getContentType(), 'application/json')) {
            $data = json_decode($data);
        }

        $controller = new $controllerClassName($this->getContainer(), $request->getMethod(), $controllerName);

        if ($actionName == 'index') {
            $actionName = $controllerClassName::$defaultAction;
        }

        $actionNameUcfirst = ucfirst($actionName);

        $beforeMethodName = 'before' . $actionNameUcfirst;
        $actionMethodName = 'action' . $actionNameUcfirst;
        $afterMethodName = 'after' . $actionNameUcfirst;

        $fullActionMethodName = strtolower($request->getMethod()) . ucfirst($actionMethodName);

        if (method_exists($controller, $fullActionMethodName)) {
            $primaryActionMethodName = $fullActionMethodName;
        } else {
            $primaryActionMethodName = $actionMethodName;
        }

        if (!method_exists($controller, $primaryActionMethodName)) {
            throw new NotFound(
                "Action '$actionName' (" . $request->getMethod() .
                ") does not exist in controller '$controllerName'"
            );
        }

        if (method_exists($controller, $beforeMethodName)) {
            $controller->$beforeMethodName($params, $data, $request, $response);
        }

        // dispatch an event
        $this->dispatch(
            'beforeAction',
            $controllerName,
            'before' . ucfirst($primaryActionMethodName),
            $params,
            $data,
            $request
        );

        $result = $controller->$primaryActionMethodName($params, $data, $request, $response);

        // dispatch an event
        $this->dispatch(
            'afterAction',
            $controllerName,
            'after' . ucfirst($primaryActionMethodName),
            $params,
            $data,
            $request,
            $result
        );

        if (method_exists($controller, $afterMethodName)) {
            $controller->$afterMethodName($params, $data, $request, $response);
        }

        if (is_bool($result)) {
            return Json::encode($result);
        }

        if (is_array($result) || $result instanceof \stdClass) {
            return Json::encode($result);
        }

        return $result;
    }

    /**
     * @param string $action
     * @param string $controller
     * @param string $method
     * @param mixed  $params
     * @param mixed  $data
     * @param mixed  $request
     * @param mixed  $result
     */
    protected function dispatch(
        string $action,
        string $controller,
        string $method,
        &$params,
        &$data,
        $request,
        &$result = null
    ): void {
        // prepare arguments
        $arguments = [
            'controller' => $controller,
            'action'     => $method,
            'params'     => $params,
            'data'       => $data,
            'request'    => $request
        ];
        if (!is_null($result)) {
            $arguments['result'] = $result;
        }

        // create an event
        $event = new Event($arguments);

        // dispatch an event
        $this
            ->getContainer()
            ->get('eventManager')
            ->dispatch('Controller', $action, $event);

        // set data
        $params = $event->getArgument('params');
        $data = $event->getArgument('data');
        if (!is_null($result)) {
            $result = $event->getArgument('result');
        }
    }

    /**
     * Get container
     *
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
