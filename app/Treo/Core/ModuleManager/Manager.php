<?php

declare(strict_types=1);

namespace Treo\Core\ModuleManager;

use Espo\Core\Exceptions\Error;
use Treo\Core\Container;
use Treo\Services\Composer;

/**
 * Class Manager
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Manager
{
    /**
     * @var array
     */
    public const CORE_MODULES = ['ColoredFields', 'Multilang'];

    /**
     * @var array|null
     */
    private $modules = null;

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
     * Get modules
     *
     * @return array
     * @throws Error
     * @throws \ReflectionException
     */
    public function getModules(): array
    {
        if (is_null($this->modules)) {
            $this->modules = [];

            // prepare path
            $path = 'data/modules.json';

            // parse data
            $data = self::CORE_MODULES;
            if (file_exists($path)) {
                $data = array_merge($data, json_decode(file_get_contents($path), true));
            }

            // load modules
            if (!empty($data)) {
                foreach ($data as $module) {
                    // prepare class name
                    $className = "\\$module\\Module";
                    if (property_exists($className, 'isTreoModule')) {
                        // prepare base path
                        $path = (new \ReflectionClass($className))->getFileName();

                        // get module path
                        $modulePath = '';
                        while (empty($modulePath)) {
                            $path = dirname($path);
                            if (file_exists($path . "/composer.json")) {
                                $modulePath = $path . "/";
                            }

                            if ($path == '/') {
                                throw new Error('Error at modules loader');
                            }
                        }

                        $this->modules[$module] = new $className(
                            $module,
                            $modulePath,
                            $this->getPackage($module),
                            $this->container
                        );
                    }
                }
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
                    if (!empty($package['extra']['treoId']) && $package['extra']['treoId'] == $id) {
                        return $package;
                    }
                }
            }
        }

        return [];
    }
}
