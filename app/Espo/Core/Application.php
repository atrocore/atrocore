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

namespace Espo\Core;

use Espo\Core\Utils\Api\Auth as ApiAuth;
use Espo\Core\Utils\Auth;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Route;
use Espo\Entities\Portal;
use Espo\ORM\EntityManager;
use Espo\Services\Installer;

class Application
{
    public const CONFIG_PATH = 'data/portals.json';

    public const COMPOSER_LOG_FILE = 'data/treo-composer.log';

    /**
     * @var null|array
     */
    protected static $urls = null;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Portal|null
     */
    protected $portal = null;

    /**
     * @var string|null
     */
    protected $clientPortalId = null;

    /**
     * Get portals url config file data
     *
     * @return array
     */
    public static function getPortalUrlFileData(): array
    {
        if (is_null(self::$urls)) {
            // prepare result
            self::$urls = [];

            if (file_exists(self::CONFIG_PATH)) {
                $json = file_get_contents(self::CONFIG_PATH);
                if (!empty($json)) {
                    self::$urls = Json::decode($json, true);
                }
            }
        }

        return self::$urls;
    }

    /**
     * Set data to portal url config file
     *
     * @param array $data
     */
    public static function savePortalUrlFile(array $data): void
    {
        file_put_contents(self::CONFIG_PATH, Json::encode($data));
    }

    /**
     * Is system updating?
     *
     * @return bool
     */
    public static function isSystemUpdating(): bool
    {
        return file_exists(self::COMPOSER_LOG_FILE);
    }

    /**
     * Application constructor.
     */
    public function __construct()
    {
        // define path to core app
        if (!defined('CORE_PATH')) {
            define('CORE_PATH', dirname(dirname(__DIR__)));
        }

        // set timezone
        date_default_timezone_set('UTC');

        // set container
        $this->container = new Container();

        // set log
        $GLOBALS['log'] = $this->getContainer()->get('log');
    }

    /**
     * Run App
     */
    public function run()
    {
        if (!empty($query = $this->getQuery())) {
            /** @var bool $show404 */
            $show404 = true;

            // for api
            if (preg_match('/^api\/v1\/(.*)$/', $query)) {
                $show404 = false;
                $this->runApi($query);
            }

            // generate openapi json
            if (preg_match('/^openapi\.json$/', $query)) {
                $this->showOpenApiJson();
            }

            // for portal
            $portalId = array_search($this->getConfig()->get('siteUrl', '') . '/' . $query, self::getPortalUrlFileData());
            if (!empty($portalId)) {
                $show404 = false;
                $this->clientPortalId = $portalId;
            }

            if ($show404) {
                $this->createAndDisplayThumb($query);

                header('HTTP/1.0 404 Not Found');
                exit();
            }
        }

        // for client
        $this->runClient();
    }

    /**
     * Run console
     *
     * @param array $argv
     */
    public function runConsole(array $argv)
    {
        // unset file path
        if (isset($argv[0])) {
            unset($argv[0]);
        }

        $this
            ->getContainer()
            ->get('consoleManager')
            ->run(implode(' ', $argv));
    }

    /**
     * Get container
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Is installed
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return file_exists($this->getConfig()->getConfigPath()) && $this->getConfig()->get('isInstalled');
    }

    protected function showOpenApiJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo Json::encode((new OpenApiGenerator($this->getContainer()))->getData());
        exit;
    }

    /**
     * Run API
     *
     * @param string $url
     */
    protected function runApi(string $url)
    {
        // for installer
        if (!$this->isInstalled()) {
            $this->runInstallerApi();
        }

        if (self::isSystemUpdating()) {
            $this->logoutAll();
        }

        if ($this->getConfig()->get('disableCorsPolicy', false)) {
            header('Access-Control-Allow-Origin: *');
            header(
                "Access-Control-Allow-Headers: Origin, Content-Type, Accept, Access-Control-Request-Method,Access-Control-Request-Headers, Authorization, Authorization-Token, Authorization-Token-Idletime, Authorization-Token-Lifetime"
            );
            if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == "OPTIONS") {
                header("HTTP/1.1 200 OK");
                exit;
            }
        }

        // prepare base route
        $baseRoute = '/api/v1';

        // for portal api
        if (preg_match('/^api\/v1\/portal-access\/(.*)\/.*$/', $url)) {
            // parse uri
            $matches = explode('/', str_replace('api/v1/portal-access/', '', $url));

            // init portal container
            $this->initPortalContainer($matches[0]);

            // prepare base route
            $baseRoute = '/api/v1/portal-access';
        }

        $this->routeHooks();
        $this->initRoutes($baseRoute);
        $this->getSlim()->run();
        exit;
    }

    /**
     * Create and display thumb if it needs
     *
     * @param string $path
     */
    protected function createAndDisplayThumb(string $path): void
    {
        if ($this->isInstalled() && !empty($attachment = $this->getContainer()->get('Thumbnail')->createThumbnailByPath($path))) {
            $content = file_get_contents($path);
            $fileType = $attachment->get('type');
            $fileName = $attachment->get('name');

            header('Content-Disposition:inline;filename="' . $fileName . '"');
            if (!empty($fileType)) {
                header('Content-Type: ' . $fileType);
            }
            header('Pragma: public');
            header('Cache-Control: max-age=360000, must-revalidate');
            $fileSize = mb_strlen($content, "8bit");
            if ($fileSize) {
                header('Content-Length: ' . $fileSize);
            }
            echo $content;
            exit;
        }
    }

    /**
     * Run client
     */
    protected function runClient()
    {
        // for installer
        if (!$this->isInstalled()) {
            $this->runInstallerClient();
        }

        if (self::isSystemUpdating()) {
            $this->display('client/html/updating.html', ['year' => date('Y'), 'logFile' => self::COMPOSER_LOG_FILE]);
        }

        // for entryPoint
        if (!empty($_GET['entryPoint'])) {
            $this->runEntryPoint($_GET['entryPoint']);
            exit;
        }

        // prepare client vars
        $vars = [
            'classReplaceMap' => json_encode($this->getMetadata()->get(['app', 'clientClassReplaceMap'], [])),
            'year'            => date('Y')
        ];

        if (!empty($this->clientPortalId)) {
            // init portal container
            $this->initPortalContainer($this->clientPortalId);

            // prepare client vars
            $vars['portalId'] = $this->clientPortalId;

            // load client
            $this->display('client/html/portal.html', $vars);
        }

        $this->display('client/html/main.html', $vars);
    }

    /**
     * Run entry point
     *
     * @param string $entryPoint
     * @param array  $data
     */
    protected function runEntryPoint(string $entryPoint, $data = [])
    {
        if (empty($entryPoint)) {
            throw new \Error();
        }

        // get portal id
        $portalId = null;
        if (!empty($_GET['portalId'])) {
            $portalId = $_GET['portalId'];
        }
        if (!empty($_COOKIE['auth-token'])) {
            $token = $this
                ->getEntityManager()
                ->getRepository('AuthToken')
                ->where(['token' => $_COOKIE['auth-token']])
                ->findOne();
            if ($token && $token->get('portalId')) {
                $portalId = $token->get('portalId');
            }
        }

        // for portal
        if (!empty($portalId)) {
            // init portal container
            $this->initPortalContainer((string)$portalId);
        }

        $slim = $this->getSlim();
        $container = $this->getContainer();

        $slim->any('.*', function () {
        });

        // create entryPointManager
        $entryPointManager = new EntryPointManager($container);

        try {
            $authRequired = $entryPointManager->checkAuthRequired($entryPoint);
            $authNotStrict = $entryPointManager->checkNotStrictAuth($entryPoint);
            $auth = new Auth($this->container, $authNotStrict);
            $apiAuth = new ApiAuth($auth, $authRequired, true);
            $slim->add($apiAuth);

            $slim->hook('slim.before.dispatch', function () use ($entryPoint, $entryPointManager, $container, $data) {
                $entryPointManager->run($entryPoint, $data);
            });

            $slim->run();
        } catch (\Exception $e) {
            $container->get('output')->processError($e->getMessage(), $e->getCode(), true, $e);
        }
    }

    /**
     * Get route list
     *
     * @return mixed
     */
    protected function getRouteList()
    {
        $routes = new Route($this->getContainer()->get('fileManager'), $this->getContainer()->get('moduleManager'), $this->getContainer()->get('dataManager'));
        $routeList = $routes->getAll();

        if (!empty($this->getContainer()->get('portal'))) {
            foreach ($routeList as $i => $route) {
                if (isset($route['route'])) {
                    if ($route['route'][0] !== '/') {
                        $route['route'] = '/' . $route['route'];
                    }
                    $route['route'] = '/:portalId' . $route['route'];
                }
                $routeList[$i] = $route;
            }
        }

        return $routeList;
    }

    /**
     * Run API for installer
     */
    protected function runInstallerApi()
    {
        // prepare request
        $request = $this->getSlim()->request();

        // prepare action
        $action = str_replace("/api/v1/Installer/", "", $request->getPathInfo());

        try {
            $result = $this->getContainer()->get('controllerManager')->process('Installer', $action, [], $request->getBody(), $request);
        } catch (\Throwable $e) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
            exit;
        }

        header('Content-Type: application/json');
        echo $result;
        exit;
    }

    /**
     * Run client for installer
     */
    protected function runInstallerClient()
    {
        $result = ['status' => false, 'message' => ''];

        // check permissions and generate config
        try {
            /** @var Installer $installer */
            $installer = $this->getContainer()->get('serviceFactory')->create('Installer');
            $result['status'] = $installer->checkPermissions();
        } catch (\Exception $e) {
            $result['status'] = 'false';
            $result['message'] = $e->getMessage();
        }

        // prepare vars
        $vars = [
            'applicationName' => 'AtroCore',
            'status'          => $result['status'],
            'message'         => $result['message'],
            'classReplaceMap' => json_encode($this->getMetadata()->get(['app', 'clientClassReplaceMap'], [])),
            'year'            => date('Y')
        ];

        $this->display('client/html/installation.html', $vars);
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
     * Route hooks
     */
    protected function routeHooks()
    {
        $container = $this->getContainer();
        $slim = $this->getSlim();

        try {
            $auth = new Auth($container);
        } catch (\Exception $e) {
            $container->get('output')->processError($e->getMessage(), $e->getCode(), false, $e);
        }

        $apiAuth = new ApiAuth($auth);

        $this->getSlim()->add($apiAuth);
        $this->getSlim()->hook('slim.before.dispatch', function () use ($slim, $container) {
            $route = $slim->router()->getCurrentRoute();
            $conditions = $route->getConditions();

            if (isset($conditions['useController']) && $conditions['useController'] == false) {
                return;
            }

            $routeOptions = call_user_func($route->getCallable());
            $routeKeys = is_array($routeOptions) ? array_keys($routeOptions) : array();

            if (!in_array('controller', $routeKeys, true)) {
                return $container->get('output')->render($routeOptions);
            }

            $params = $route->getParams();
            $data = $slim->request()->getBody();

            foreach ($routeOptions as $key => $value) {
                if (strstr($value, ':')) {
                    $paramName = str_replace(':', '', $value);
                    $value = $params[$paramName];
                }
                $controllerParams[$key] = $value;
            }

            $params = array_merge($params, $controllerParams);

            $controllerName = ucfirst($controllerParams['controller']);

            if (!empty($controllerParams['action'])) {
                $actionName = $controllerParams['action'];
            } else {
                $httpMethod = strtolower($slim->request()->getMethod());
                $crudList = $container->get('config')->get('crud');
                $actionName = $crudList[$httpMethod];
            }

            try {
                $controllerManager = $this->getContainer()->get('controllerManager');
                $result = $controllerManager->process($controllerName, $actionName, $params, $data, $slim->request(), $slim->response());
                $container->get('output')->render($result);
            } catch (\Exception $e) {
                $container->get('output')->processError($e->getMessage(), $e->getCode(), false, $e);
            }
        });

        $this->getSlim()->hook('slim.after.router', function () use (&$slim) {
            $slim->contentType('application/json');

            $res = $slim->response();
            $res->header('Expires', '0');
            $res->header('Last-Modified', gmdate("D, d M Y H:i:s") . " GMT");
            $res->header('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            $res->header('Pragma', 'no-cache');
        });
    }

    /**
     * Init routes
     *
     * @param string $baseRoute
     */
    protected function initRoutes(string $baseRoute)
    {
        $crudList = array_keys($this->getConfig()->get('crud'));

        foreach ($this->getRouteList() as $route) {
            $method = strtolower($route['method']);
            if (!in_array($method, $crudList) && $method !== 'options') {
                $message = "Route: Method [$method] does not exist. Please check your route [" . $route['route'] . "]";

                $GLOBALS['log']->error($message);
                continue;
            }

            $currentRoute = $this->getSlim()->$method($baseRoute . $route['route'], function () use ($route) {
                return $route['params'];
            });

            if (isset($route['conditions'])) {
                $currentRoute->conditions($route['conditions']);
            }
        }
    }

    /**
     * Get slim
     *
     * @return mixed
     */
    protected function getSlim()
    {
        return $this->getContainer()->get('slim');
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    /**
     * @param string $portalId
     *
     * @throws \Exception
     */
    private function initPortalContainer(string $portalId): void
    {
        // find portal
        $portal = $this
            ->getContainer()
            ->get('entityManager')
            ->getEntity('Portal', $portalId);

        if (!empty($portal) && $portal->get('isActive')) {
            $this->getContainer()->setPortal($portal);
        } else {
            throw new \Exception('No such portal');
        }
    }

    /**
     * @param string $template
     * @param array  $vars
     */
    private function display(string $template, array $vars)
    {
        $this
            ->getContainer()
            ->get('clientManager')
            ->display(null, $template, $vars);
        exit;
    }

    /**
     * @return string
     */
    private function getQuery(): string
    {
        if (empty($_GET['treoq'])) {
            return '';
        }

        /** @var string $query */
        $query = $_GET['treoq'];

        // unset query from GET
        unset($_GET['treoq']);

        // prepare redirect query string
        if (!empty($_SERVER['REDIRECT_QUERY_STRING'])) {
            $_SERVER['REDIRECT_QUERY_STRING'] = str_replace("treoq=$query&", '', $_SERVER['REDIRECT_QUERY_STRING']);
        }

        // prepare query string
        if (!empty($_SERVER['QUERY_STRING'])) {
            $_SERVER['QUERY_STRING'] = str_replace("treoq=$query&", '', $_SERVER['QUERY_STRING']);
        }

        return $query;
    }

    /**
     * Logout all users
     */
    private function logoutAll(): void
    {
        $this->getPDO()->exec("DELETE FROM auth_token WHERE lifetime IS NULL AND idle_time IS NULL");
    }

    private function getPDO(): \PDO
    {
        return $this->getContainer()->get('pdo');
    }

    private function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }
}
