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

use Atro\Services\Installer;
use Espo\Core\EntryPointManager;
use Espo\Core\Utils\Api\Auth as ApiAuth;
use Espo\Core\Utils\Auth;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Route;
use Espo\ORM\EntityManager;
use Atro\Services\Composer;

class Application
{
    public const COMPOSER_LOG_FILE = 'data/composer.log';

    protected static ?array $urls = null;

    protected Container $container;

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

        if (!defined('VENDOR_PATH')) {
            define('VENDOR_PATH', dirname(dirname(dirname(dirname(__DIR__)))));
        }

        // set timezone
        date_default_timezone_set('UTC');

        $GLOBALS['track']['start'] = microtime(true);

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

            if ($show404) {
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

        if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === "OPTIONS") {
            header("HTTP/1.1 200 OK");
            exit;
        }

        // prepare base route
        $baseRoute = '/api/v1';

        $this->routeHooks();
        $this->initRoutes($baseRoute);
        $this->getSlim()->run();
        exit;
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
            $this->display('client/html/updating.html', ['year' => date('Y'), 'logFile' => self::COMPOSER_LOG_FILE, 'version' => Composer::getCoreVersion()]);
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
            'year'            => date('Y'),
            'version'         => Composer::getCoreVersion()
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

            /** @var \Espo\Core\Utils\Api\Output $output */
            $output = $container->get('output');

            $routeOptions = call_user_func($route->getCallable());
            $routeKeys = is_array($routeOptions) ? array_keys($routeOptions) : array();

            if (!in_array('controller', $routeKeys, true)) {
                return $output->render($routeOptions);
            }

            $params = $route->getParams();
            $data = $slim->request()->getBody();

            $controllerParams = [];
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
                $output->render($result);
            } catch (\Exception $e) {
                $output->processError($e->getMessage(), $e->getCode(), false, $e);
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
        $name = 'atroq';

        // for backward compatibility
        if (!empty($_GET['treoq'])) {
            $name = 'treoq';
        }

        if (empty($_GET[$name])) {
            return '';
        }

        /** @var string $query */
        $query = $_GET[$name];

        // unset query from GET
        unset($_GET[$name]);

        // prepare redirect query string
        if (!empty($_SERVER['REDIRECT_QUERY_STRING'])) {
            $_SERVER['REDIRECT_QUERY_STRING'] = str_replace("$name=$query&", '', $_SERVER['REDIRECT_QUERY_STRING']);
        }

        // prepare query string
        if (!empty($_SERVER['QUERY_STRING'])) {
            $_SERVER['QUERY_STRING'] = str_replace("$name=$query&", '', $_SERVER['QUERY_STRING']);
        }

        return $query;
    }

    /**
     * Logout all users
     */
    private function logoutAll(): void
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getContainer()->get('connection');

        $connection
            ->createQueryBuilder()
            ->delete('auth_token')
            ->andwhere('lifetime IS NULL')
            ->andWhere('idle_time IS NULL')
            ->executeQuery();
    }

    private function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }
}
