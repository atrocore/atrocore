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

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\NotFound;

/**
 * Resolves and runs entry points: classes from Atro/EntryPoints and from EntryPoints of every module.
 * A module entry point overrides the core one with the same name.
 */
class EntryPointManager
{
    private ?array $classes = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function checkAuthRequired(string $name): bool
    {
        return $this->getClassName($name)::$authRequired;
    }

    public function run(string $name, array $data = []): void
    {
        $className = $this->getClassName($name);
        $entryPoint = new $className($this->container);

        $event = $this->container
            ->get('eventManager')
            ->dispatch('EntryPoint', 'run', new Event(['name' => $name, 'data' => $data]));

        $entryPoint->run($event->getArgument('data'));
    }

    private function getClassName(string $name): string
    {
        if ($this->classes === null) {
            $this->classes = $this->loadClasses();
        }

        $className = $this->classes[ucfirst($name)] ?? null;
        if ($className === null) {
            throw new NotFound();
        }

        return $className;
    }

    private function loadClasses(): array
    {
        $classes = $this->findClasses(CORE_PATH . '/Atro/EntryPoints', 'Atro\EntryPoints');

        foreach ($this->container->get('moduleManager')->getModules() as $module) {
            $classes = array_merge(
                $classes,
                $this->findClasses($module->getAppPath() . 'EntryPoints', $module->getId() . '\EntryPoints')
            );
        }

        return $classes;
    }

    private function findClasses(string $dir, string $namespace): array
    {
        $classes = [];

        foreach (glob($dir . '/*.php') as $file) {
            $name = basename($file, '.php');
            $className = "$namespace\\$name";

            if (class_exists($className) && !(new \ReflectionClass($className))->isAbstract()) {
                $classes[$name] = $className;
            }
        }

        return $classes;
    }
}
