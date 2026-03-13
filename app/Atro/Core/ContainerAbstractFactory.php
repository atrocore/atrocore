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

use Atro\Core\Factories\FactoryInterface as AtroCoreFactory;
use Espo\Core\Interfaces\Injectable;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

class ContainerAbstractFactory implements AbstractFactoryInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        return class_exists($this->container->resolveClass($requestedName));
    }

    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): object
    {
        $className = $this->container->resolveClass($requestedName);

        if (is_a($className, AtroCoreFactory::class, true)) {
            return (new $className())->create($this->container);
        }

        $reflectionClass = new \ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor !== null && !empty($params = $constructor->getParameters())) {
            $input = [];
            foreach ($params as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $depClassName = $type->getName();
                    $input[] = is_a($depClassName, Container::class, true)
                        ? $this->container
                        : $this->container->get($depClassName);
                }
            }
            return new $className(...$input);
        }

        if (is_a($className, Injectable::class, true)) {
            $instance = new $className();
            foreach ($instance->getDependencyList() as $dependency) {
                $instance->inject($dependency, $this->container->get($dependency));
            }
            return $instance;
        }

        return new $className();
    }
}
