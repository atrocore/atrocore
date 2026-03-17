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

use Atro\Core\Container\AbstractFactory as ContainerAbstractFactory;
use Atro\Core\Container\ServiceManagerConfig;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Services\Installer;
use Espo\Core\EntryPointManager;
use Espo\Core\Utils\Auth;
use Atro\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Atro\Core\Utils\Metadata;
use Atro\Services\Composer;
use GuzzleHttp\Psr7\ServerRequest;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Atro\Core\Factories\HttpPipeline;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stratigility\MiddlewarePipe;

final class Application
{
    public const COMPOSER_LOG_FILE = 'public/data/composer.log';

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

        // Bootstrap DI: Application → SM (primary) → Container (legacy adapter)
        $smConfig      = new ServiceManagerConfig();
        $container     = new Container($smConfig);
        $abstractFactory = new ContainerAbstractFactory($smConfig, $container);

        $sm = new ServiceManager(
            [
                'abstract_factories' => [$abstractFactory],
                'aliases'            => $smConfig->getAliases(),
                'services'           => ['container' => $container],
                'factories'          => [
                    MiddlewarePipe::class => HttpPipeline::class,
                    'user'               => fn($c) => $c->get(UserContext::class)->getUser(),
                    'acl'                => fn($c) => $c->get(UserContext::class)->getAcl($c->get('aclManager')),
                ],
                'shared'             => ['user' => false, 'acl' => false],
            ],
            $container
        );

        $container->setSm($sm);

        $moduleManager = new ModuleManager($sm);
        $sm->setService('moduleManager', $moduleManager);
        foreach ($moduleManager->getModules() as $module) {
            $module->onLoad();
        }

        $this->container = $container;

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

            if (preg_match_all('/^thumbnail\/(.*)\\/(.*)\.(.*)$/', $query, $matches)) {
                $_GET['size'] = $matches[1][0];
                $_GET['id'] = $matches[2][0];
                $this->runEntryPoint('thumbnail');
                exit;
            }

            if (preg_match_all('/^images\/(.*)\.(.*)$/', $query, $matches)) {
                $_GET['id'] = $matches[1][0];
                $this->runEntryPoint('image');
                exit;
            }

            if (preg_match_all('/^downloads\/(.*)\.(.*)$/', $query, $matches)) {
                $_GET['id'] = $matches[1][0];
                $this->runEntryPoint('download');
                exit;
            }

            if (preg_match_all('/^sharings\/(.*)\.(.*)$/', $query, $matches)) {
                $_GET['id'] = $matches[1][0];
                $this->runEntryPoint('sharing');
                exit;
            }

            if ($show404) {
                header('HTTP/1.0 404 Not Found');
                exit;
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
        echo Json::encode($this->getContainer()->get(OpenApiGenerator::class)->getFullSchema());
        exit;
    }

    /**
     * Run API
     */
    protected function runApi(string $url): void
    {
        if (!$this->isInstalled()) {
            $this->runInstallerApi();
        }

        if (self::isSystemUpdating()) {
            $this->logoutAll();
        }

        if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('HTTP/1.1 200 OK');
            exit;
        }

        $request  = ServerRequest::fromGlobals();
        $pipeline = $this->getContainer()->get(MiddlewarePipe::class);
        $response = $pipeline->handle($request);

        (new SapiEmitter())->emit($response);
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
            $this->display('public/client/html/updating.html', ['year' => date('Y'), 'logFile' => 'data/composer.log', 'version' => Composer::getCoreVersion()]);
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

        $this->display('public/client/html/main.html', $vars);
    }

    /**
     * Run entry point
     */
    protected function runEntryPoint(string $entryPoint, array $data = []): void
    {
        if (empty($entryPoint)) {
            throw new \Error();
        }

        $container         = $this->getContainer();
        $entryPointManager = new EntryPointManager($container);

        try {
            $authRequired  = $entryPointManager->checkAuthRequired($entryPoint);
            $authNotStrict = $entryPointManager->checkNotStrictAuth($entryPoint);
            $auth          = new Auth($container, $authNotStrict);

            [$username, $password] = $this->extractAuthCredentials();

            if (!$authRequired) {
                if ($username && $password) {
                    try {
                        $auth->login($username, $password);
                    } catch (\Exception $e) {
                        // optional auth — ignore
                    }
                } else {
                    $auth->useNoAuth();
                }
            } elseif ($username && $password) {
                try {
                    $ok = $auth->login($username, $password);
                    if (!$ok) {
                        header('HTTP/1.1 401 Unauthorized');
                        exit;
                    }
                } catch (\Atro\Core\Exceptions\Unauthorized $e) {
                    header('HTTP/1.1 401 Unauthorized');
                    header('Password-Expired: true');
                    exit;
                } catch (\Exception $e) {
                    header('HTTP/1.0 ' . ($e->getCode() ?: 500) . ' Error');
                    exit;
                }
            } else {
                header('HTTP/1.1 401 Unauthorized');
                exit;
            }

            $entryPointManager->run($entryPoint, $data);
        } catch (\Exception $e) {
            $container->get('output')->processError($e->getMessage(), $e->getCode(), true, $e);
        }
    }

    /**
     * Run API for installer
     */
    protected function runInstallerApi(): void
    {
        $request = ServerRequest::fromGlobals();
        $action  = str_replace('/api/v1/Installer/', '', $request->getUri()->getPath());

        try {
            $result = $this->getContainer()->get('controllerManager')->process('Installer', $action, [], $request);
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

        $this->display('public/client/html/installation.html', $vars);
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

    private function extractAuthCredentials(): array
    {
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return explode(':', base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6)), 2);
        }

        if (!empty($_SERVER['HTTP_AUTHORIZATION_TOKEN'])) {
            return explode(':', base64_decode($_SERVER['HTTP_AUTHORIZATION_TOKEN']), 2);
        }

        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            return [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ?? ''];
        }

        if (!empty($_COOKIE['auth-username']) && !empty($_COOKIE['auth-token'])) {
            return [$_COOKIE['auth-username'], $_COOKIE['auth-token']];
        }

        return [null, null];
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
}
