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

namespace Atro\Core\ModuleManager;

use Atro\Core\Container;
use Atro\Services\Composer;

/**
 * Class Manager
 */
class Manager
{
    const FILE_PATH = 'data/modules.json';

    /**
     * @var array
     */
    private $modules = [];

    /**
     * @var Container
     */
    private $container;

    /**
     * Prepare version
     *
     * @param string $version
     *
     * @return string
     */
    public static function prepareVersion(string $version): string
    {
        return str_replace('v', '', $version);
    }

    /**
     * Manager constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return bool
     */
    public function isLoaded(): bool
    {
        foreach ($this->getModulesList() as $module) {
            if (!isset($this->modules[$module])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function getModulesList(): array
    {
        $data = [];

        if (file_exists(self::FILE_PATH)) {
            $data = json_decode(file_get_contents(self::FILE_PATH), true);
        }

        return $data;
    }

    /**
     * Get modules
     *
     * @return array
     */
    public function getModules(): array
    {
        foreach ($this->getModulesList() as $module) {
            if (!isset($this->modules[$module])) {
                $this->loadModule($module);
            }
        }

        return $this->modules;
    }

    /**
     * Get module
     *
     * @param string $id
     *
     * @return AbstractModule|null
     */
    public function getModule(string $id): ?AbstractModule
    {
        foreach ($this->getModules() as $name => $module) {
            if ($name == $id) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @param string $name
     *
     * @return AfterInstallAfterDelete
     */
    public function getModuleInstallDeleteObject(string $name): AfterInstallAfterDelete
    {
        $class = sprintf('\\%s\\Event', $name);
        if (!class_exists($class) || !is_a($class, AfterInstallAfterDelete::class, true)) {
            $class = AfterInstallAfterDelete::class;
        }

        return new $class($this->container);
    }

    /**
     * @param string $module
     */
    protected function loadModule(string $module): void
    {
        // prepare class name
        $className = "\\$module\\Module";
        if (is_a($className, AbstractModule::class, true)) {
            try {
                $path = (new \ReflectionClass($className))->getFileName();
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Module Manager ERROR: Can't load $className");
                return;
            }

            $modulePath = '';
            while (empty($modulePath)) {
                $path = dirname($path);
                if (file_exists($path . "/composer.json")) {
                    $modulePath = $path . "/";
                }

                if ($path == '/') {
                    $GLOBALS['log']->error("Module Manager ERROR: Can't find composer.json file");
                    return;
                }
            }

            $this->modules[$module] = new $className($module, $modulePath, $this->getPackage($module), $this->container);
        }
    }

    /**
     * Get composer package
     *
     * @param string $id
     *
     * @return array
     */
    private function getPackage(string $id): array
    {
        if (file_exists(Composer::$composerLock)) {
            $data = json_decode(file_get_contents(Composer::$composerLock), true);
            if (!empty($data['packages'])) {
                foreach ($data['packages'] as $package) {
                    if (!empty($package['extra']['atroId']) && $package['extra']['atroId'] == $id) {
                        return $package;
                    }
                    if (!empty($package['extra']['treoId']) && $package['extra']['treoId'] == $id) {
                        return $package;
                    }
                }
            }
        }

        return [];
    }
}
