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

namespace Treo\Core\EventManager;

use Espo\Core\DataManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Treo\Core\Container;

/**
 * Manager class
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
        /** @var DataManager $dataManager */
        $dataManager = $this->container->get('dataManager');

        if (empty($data = $dataManager->getCacheData('listeners'))) {
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

            // caching
            $dataManager->setCacheData('listeners', $data);
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
