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

use Atro\Core\Exceptions\Error;
use Atro\Core\Services\AbstractRecordService;

/**
 * Creates the services: the class set in app.services, then the service of the module of the entity, then the service
 * of the core, then the template of the entity type. A derivative entity uses the service of its primary entity.
 * A service is created once per request.
 */
class ServiceFactory
{
    private array $services = [];
    private array $classNames = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function checkExists(string $name): bool
    {
        return $this->getClassName($name) !== null;
    }

    public function create(string $name): object
    {
        if (!isset($this->services[$name])) {
            $className = $this->getClassName($name);
            if ($className === null) {
                throw new Error("Service '$name' was not found");
            }

            $service = new $className($this->container);
            if ($service instanceof AbstractRecordService) {
                $service->setEntityType($name);
            }

            $this->services[$name] = $service;
        }

        return $this->services[$name];
    }

    private function getClassName(string $name): ?string
    {
        if (!array_key_exists($name, $this->classNames)) {
            $this->classNames[$name] = $this->findClassName($name);
        }

        return $this->classNames[$name];
    }

    private function findClassName(string $name): ?string
    {
        $metadata = $this->container->get('metadata');

        $scope = $metadata->get("scopes.$name.primaryEntityId") ?: $name;
        $module = $metadata->get(['scopes', $scope, 'module'], 'Atro');
        $type = $metadata->get(['scopes', $scope, 'type']);

        $candidates = [
            $metadata->get(['app', 'services', $name]),
            "$module\\Services\\$scope",
            "Atro\\Services\\$scope",
            $type ? "Atro\\Core\\Templates\\Services\\$type" : null,
        ];

        foreach ($candidates as $className) {
            if (!empty($className) && class_exists($className)) {
                return $className;
            }
        }

        return null;
    }
}
