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

namespace Atro\Core\EventManager;

use Atro\Core\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Manager
{
    private Container $container;
    private EventDispatcher $eventDispatcher;
    private bool $isLoaded = false;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->eventDispatcher = new EventDispatcher();
    }

    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
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

        return $this->getEventDispatcher()->dispatch($event, $eventName);
    }

    public function addListener($eventName, $listener)
    {
        $this->getEventDispatcher()->addListener($eventName, $listener);
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
        $dataManager = $this->container->get('dataManager');

        if (!empty($data = $dataManager->getCacheData('listeners'))) {
            return $data;
        }

        $listeners = [];

        $corePath = CORE_PATH . '/Atro/Listeners';
        if (file_exists($corePath)) {
            $this->parseDir('Atro', $corePath, $listeners);
        }

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

        // caching
        $dataManager->setCacheData('listeners', $data);

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
