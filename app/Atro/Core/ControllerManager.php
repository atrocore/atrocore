<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core;

use Atro\Core\EventManager\Manager;
use Atro\Core\Exceptions\BadRequest;
use Espo\Core\EventManager\Event;
use Atro\Core\Exceptions\NotFound;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Slim\Http\Request;
use Slim\Http\Response;

class ControllerManager
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public static function getControllerClassName(string $controllerName, Metadata $metadata): string
    {
        $className = Util::normilizeClassName($controllerName);

        $controllerClassName = "\\Atro\\Controllers\\$className";

        // for modules
        $moduleName = $metadata->getScopeModuleName($controllerName);
        if (!empty($moduleName)) {
            $moduleControllerClassName = "\\$moduleName\\Controllers\\$className";
            if (class_exists($moduleControllerClassName)) {
                $controllerClassName = $moduleControllerClassName;
            }
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

    public function process(
        string $controllerName,
        string $actionName,
        array $params,
        $data,
        ?Request $request,
        ?Response $response
    ): string {
        $controllerClassName = self::getControllerClassName($controllerName, $this->getMetadata());
        if ($controllerName == 'Layout') {
            $controllerClassName = '\\Atro\\Controllers\\Layout';
        }

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

        $this->dispatch(
            'beforeAction',
            $controllerName,
            'before' . ucfirst($primaryActionMethodName),
            $params,
            $data,
            $request
        );

        $result = $controller->$primaryActionMethodName($params, $data, $request, $response);

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

    protected function dispatch(
        string $action,
        string $controller,
        string $method,
        &$params,
        &$data,
        $request,
        &$result = null
    ): void {
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

        $event = new Event($arguments);

        $this
            ->getEventManager()
            ->dispatch('Controller', $action, $event);

        $params = $event->getArgument('params');
        $data = $event->getArgument('data');
        if (!is_null($result)) {
            $result = $event->getArgument('result');
        }
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }
}
