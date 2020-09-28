<?php

namespace Espo\Core\Utils\Api;

class Slim extends \Slim\Slim
{
    public function __construct(array $userSettings = array())
    {
        // Setup IoC container
        $this->container = new \Slim\Helper\Set();
        $this->container['settings'] = array_merge(static::getDefaultSettings(), $userSettings);

        // Default environment
        $this->container->singleton('environment', function ($c) {
            /* ESPOCRM: change Environment class */
            //return \Slim\Environment::getInstance();
            return \Espo\Core\Utils\Api\Slim\Environment::getInstance();
            /* ESPOCRM: end */
        });

        // Default request
        $this->container->singleton('request', function ($c) {
            return new \Slim\Http\Request($c['environment']);
        });

        // Default response
        $this->container->singleton('response', function ($c) {
            return new \Slim\Http\Response();
        });

        // Default router
        $this->container->singleton('router', function ($c) {
            return new \Slim\Router();
        });

        // Default view
        $this->container->singleton('view', function ($c) {
            $viewClass = $c['settings']['view'];
            $templatesPath = $c['settings']['templates.path'];

            $view = ($viewClass instanceOf \Slim\View) ? $viewClass : new $viewClass;
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
        $this->middleware[0]->call();

        list($status, $headers, $body) = $this->response->finalize();

        \Slim\Http\Util::serializeCookies($headers, $this->response->cookies, $this->settings);

        if (headers_sent() === false) {
            if (strpos(PHP_SAPI, 'cgi') === 0) {
                header(sprintf('Status: %s', \Slim\Http\Response::getMessageForCode($status)));
            } else {
                header(sprintf('HTTP/%s %s', $this->config('http.version'), \Slim\Http\Response::getMessageForCode($status)));
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
    }

    public function printError($error, $status)
    {
        echo static::generateTemplateMarkup($status, '<p>'.$error.'</p><a href="' . $this->request->getRootUri() . '/">Visit the Home Page</a>');
    }

}