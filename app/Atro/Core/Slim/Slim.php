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

namespace Atro\Core\Slim;

use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\OpenApiGenerator;
use Atro\Core\Slim\Http\Request;
use Atro\Core\Slim\Http\Response;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;

class Slim extends \Slim\Slim
{
    private Container $atroContainer;

    public function __construct(Container $container)
    {
        $this->atroContainer = $container;

        // Setup IoC container
        $this->container = new \Slim\Helper\Set();
        $this->container['settings'] = static::getDefaultSettings();

        // Default environment
        $this->container->singleton('environment', function ($c) {
            return \Espo\Core\Utils\Api\Slim\Environment::getInstance();
        });

        // Default request
        $this->container->singleton('request', function ($c) {
            return new Request($c['environment']);
        });

        // Default response
        $this->container->singleton('response', function ($c) {
            return new Response();
        });

        // Default router
        $this->container->singleton('router', function ($c) {
            return new \Slim\Router();
        });

        // Default view
        $this->container->singleton('view', function ($c) {
            $viewClass = $c['settings']['view'];
            $templatesPath = $c['settings']['templates.path'];

            $view = ($viewClass instanceof \Slim\View) ? $viewClass : new $viewClass;
            $view->setTemplatesDirectory($templatesPath);
            return $view;
        });

        // Default log writer
        $this->container->singleton('logWriter', function ($c) {
            $logWriter = $c['settings']['log.writer'];

            return is_object($logWriter) ? $logWriter : new \Slim\LogWriter($c['environment']['slim.errors']);
        });

        // Default log
        $this->container->singleton('log', function ($c) {
            $log = new \Slim\Log($c['logWriter']);
            $log->setEnabled($c['settings']['log.enabled']);
            $log->setLevel($c['settings']['log.level']);
            $env = $c['environment'];
            $env['slim.log'] = $log;

            return $log;
        });

        // Default mode
        $this->container['mode'] = function ($c) {
            $mode = $c['settings']['mode'];

            if (isset($_ENV['SLIM_MODE'])) {
                $mode = $_ENV['SLIM_MODE'];
            } else {
                $envMode = getenv('SLIM_MODE');
                if ($envMode !== false) {
                    $mode = $envMode;
                }
            }

            return $mode;
        };

        // Define default middleware stack
        $this->middleware = array($this);
        $this->add(new \Slim\Middleware\Flash());
        $this->add(new \Slim\Middleware\MethodOverride());

        // Make default if first instance
        if (is_null(static::getInstance())) {
            $this->setName('default');
        }
    }

    /**
     * Redefine the run method
     *
     * We no need to use a Slim handler
     */
    public function run()
    {
        $route = null;
        $validatorBuilder = null;

        // validate request
        $this->hook('slim.before.dispatch', function () use (&$route, &$validatorBuilder) {
            $route = $this->router()->getCurrentRoute();
            if (!empty($route->_routeConfig['description'])) {
                $schema = $this->atroContainer->get(OpenApiGenerator::class)->getSchemaForRoute($route->_routeConfig);
                $validatorBuilder = (new ValidatorBuilder())->fromJson(json_encode($schema));
            }
        });

        $this->middleware[0]->call();

        list($status, $headers, $body) = $this->response->finalize();

        \Slim\Http\Util::serializeCookies($headers, $this->response->cookies, $this->settings);

        if (headers_sent() === false) {
            if (strpos(PHP_SAPI, 'cgi') === 0) {
                header(sprintf('Status: %s', \Slim\Http\Response::getMessageForCode($status)));
            } else {
                header(sprintf('HTTP/%s %s', $this->config('http.version'),
                    \Slim\Http\Response::getMessageForCode($status)));
            }

            foreach ($headers as $name => $value) {
                $hValues = explode("\n", $value);
                foreach ($hValues as $hVal) {
                    header("$name: $hVal", false);
                }
            }
        }

        if (!$this->request->isHead()) {
            echo $body;
        }

//        // validate response
//        if (!empty($validatorBuilder)) {
//            try {
//                $operation = new OperationAddress($route->_routeConfig['route'], $route->_routeConfig['method']);
//                $validatorBuilder->getResponseValidator()->validate($operation, $this->response()->getPsrResponse());
//            } catch (\Throwable $e) {
//                throw new BadRequest($e->getMessage());
//            }
//        }
    }

    public function printError($error, $status)
    {
        echo static::generateTemplateMarkup($status, '<p>' . $error . '</p><a href="' . $this->request->getRootUri() . '/">Visit the Home Page</a>');
    }
}