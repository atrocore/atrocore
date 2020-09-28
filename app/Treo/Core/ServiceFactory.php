<?php
declare(strict_types=1);

namespace Treo\Core;

use Espo\Core\Exceptions\Error;
use Espo\Core\Interfaces\Injectable;
use Treo\Core\Interfaces\ServiceInterface;

/**
 * ServiceFactory class
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class ServiceFactory
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var array
     */
    private $services = [];

    /**
     * @var array
     */
    private $classNames;

    /**
     * ServiceFactory constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->classNames = $this->container->get('metadata')->get(['app', 'services'], []);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function checkExists(string $name): bool
    {
        try {
            $className = $this->getClassName($name);
        } catch (Error $e) {
            $className = null;
        }

        return !empty($className);
    }

    /**
     * @param string $name
     *
     * @return ServiceInterface
     * @throws Error
     */
    public function create(string $name): ServiceInterface
    {
        if (!isset($this->services[$name])) {
            /** @var string $className */
            $className = $this->getClassName($name);

            // create service
            $service = new $className();

            if (!$service instanceof ServiceInterface) {
                throw new Error("Service '$name' doesn't support");
            }

            if ($service instanceof Injectable) {
                foreach ($service->getDependencyList() as $name) {
                    $service->inject($name, $this->container->get($name));
                }
            }
            if ($service instanceof \Treo\Services\AbstractService) {
                $service->setContainer($this->container);
            }

            $this->services[$name] = $service;
        }

        return $this->services[$name];
    }

    /**
     * @param string $name
     *
     * @return string
     * @throws Error
     */
    protected function getClassName(string $name): string
    {
        if (!isset($this->classNames[$name])) {
            /** @var string $module */
            $module = $this->container->get('metadata')->get(['scopes', $name, 'module'], 'Espo');

            switch ($module) {
                case 'TreoCore':
                    $module = 'Treo';
                    break;
                case 'Custom':
                    $module = 'Espo\\Custom';
                    break;
            }

            $this->classNames[$name] = "\\$module\\Services\\$name";
        }

        if (!class_exists($this->classNames[$name])) {
            throw new Error("Service '$name' was not found");
        }

        return $this->classNames[$name];
    }
}
