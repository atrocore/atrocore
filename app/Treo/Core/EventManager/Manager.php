<?php

declare(strict_types=1);

namespace Treo\Core\EventManager;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Treo\Core\Container;

/**
 * Manager class
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Manager extends EventDispatcher
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var bool
     */
    private $isLoaded = false;

    /**
     * Manager constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        // call parent
        parent::__construct();

        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function dispatch($event)
    {
        // get arguments
        $args = \func_num_args();

        $eventName = null;
        if ($args == 3) {
            $eventName = \func_get_arg(0) . '.' . \func_get_arg(1);
            $event = \func_get_arg(2);
        } elseif ($args == 2) {
            $eventName = \func_get_arg(1);
        }

        return parent::dispatch($event, $eventName);
    }

    /**
     * Load all listeners
     */
    public function loadListeners(): bool
    {
        if ($this->isLoaded) {
            return true;
        }

        // load listeners
        foreach ($this->getClassNames() as $action => $rows) {
            foreach ($rows as $row) {
                try {
                    $object = new $row[0]();
                } catch (\Throwable $e) {
                    continue 1;
                }

                // set container
                if (\method_exists($object, 'setContainer')) {
                    $object->setContainer($this->container);
                }

                // add
                $this->addListener($action, [$object, $row[1]]);
            }
        }

        $this->isLoaded = true;

        return true;
    }

    /**
     * @return array
     */
    protected function getClassNames(): array
    {
        // get useCache param
        $useCache = $this->container->get('config')->get('useCache', false);

        // prepare path
        $path = 'data/cache/listeners.json';

        if ($useCache && file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
        } else {
            // prepare listeners
            $listeners = [];

            // for core
            $corePath = CORE_PATH . '/Treo/Listeners';
            if (file_exists($corePath)) {
                $this->parseDir('Treo', $corePath, $listeners);
            }

            // for modules
            foreach ($this->container->get('moduleManager')->getModules() as $id => $module) {
                $module->loadListeners($listeners);
            }

            $data = [];
            foreach ($listeners as $target => $classes) {
                foreach ($classes as $listener) {
                    // skip abstract classes
                    try {
                        $obj = new $listener;
                    } catch (\Throwable $e) {
                        continue 1;
                    }
                    if (!empty($methods = \get_class_methods($listener))) {
                        foreach ($methods as $method) {
                            if ($method != 'setContainer') {
                                $data["$target.$method"][] = [$listener, $method];
                            }
                        }
                    }
                }
            }

            if ($useCache) {
                // create dir if it needs
                if (!file_exists('data/cache')) {
                    mkdir('data/cache', 0777, true);
                }

                // save cache file
                file_put_contents($path, json_encode($data));
            }
        }

        return $data;
    }

    /**
     * @param string $id
     * @param string $dirPath
     * @param array  $listeners
     */
    private function parseDir(string $id, string $dirPath, array &$listeners): void
    {
        if (file_exists($dirPath) && is_dir($dirPath)) {
            foreach (scandir($dirPath) as $file) {
                if (!in_array($file, ['.', '..'])) {
                    // prepare name
                    $name = str_replace(".php", "", $file);

                    // push
                    $listeners[$name][] = "\\" . $id . "\\Listeners\\" . $name;
                }
            }
        }
    }
}
