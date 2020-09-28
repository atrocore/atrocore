<?php

declare(strict_types=1);

namespace Treo\Core;

use Treo\Console\AbstractConsole;
use Treo\Core\Utils\Metadata;
use Treo\Traits\ContainerTrait;

/**
 * ConsoleManager
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class ConsoleManager
{
    use ContainerTrait;

    /**
     * Run console command
     *
     * @param string $command
     */
    public function run(string $command)
    {
        if (!empty($data = $this->getRouteHandler($command))) {
            if (class_exists($data['handler'])) {
                // create handler
                $handler = new $data['handler']();

                if (!$handler instanceof AbstractConsole) {
                    // prepare message
                    $message = "Handler " . $data['handler'] . " should be instance
                     of " . AbstractConsole::class;

                    AbstractConsole::show($message, 2, true);
                }

                $handler->setContainer($this->getContainer());
                $handler->run($data['data']);
                die();
            }
            AbstractConsole::show('No such console handler as ' . $data['handler'], 2, true);
        } else {
            AbstractConsole::show('No such console command!', 2, true);
        }
    }

    /**
     * Get route handler
     *
     * @param string $command
     *
     * @return array
     */
    protected function getRouteHandler(string $command): array
    {
        // prepare result
        $result = [];

        foreach ($this->loadRoutes() as $route => $handler) {
            if ($route == $command) {
                $result = [
                    'handler' => $handler,
                    'data'    => []
                ];
            } elseif (preg_match_all("/\<(.+?)\>/is", $route, $matches)) {
                // prepare parameters
                $parameters = $matches[1];

                // prepare pattern
                $pattern = "/^{$route}$/";
                foreach ($parameters as $parameter) {
                    $pattern = str_replace("<$parameter>", "(.*)", $pattern);
                }

                if (preg_match_all($pattern, $command, $matches)) {
                    $data = [];
                    foreach ($parameters as $k => $name) {
                        $data[$name] = $matches[$k + 1][0];
                    }

                    $result = [
                        'handler' => $handler,
                        'data'    => $data
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Load routes
     *
     * @return array
     */
    protected function loadRoutes(): array
    {
        return include CORE_PATH . '/Treo/Configs/Console.php';
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
}
