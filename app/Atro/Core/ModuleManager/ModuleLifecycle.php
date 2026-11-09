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

use Atro\Core\Exceptions\Error;
use Atro\Core\UserContext;
use Atro\Core\Utils\Config;
use Doctrine\DBAL\Connection;
use Espo\ORM\EntityManager;
use Psr\Container\ContainerInterface;

/**
 * Runs the steps of the core and of the modules: the AfterInstall steps (see AfterInstallInterface) and the
 * AfterDelete steps (see AbstractAfterDelete).
 */
class ModuleLifecycle
{
    public const CORE_ID = 'Atro';
    public const AFTER_INSTALL_DIR = 'AfterInstall';
    public const AFTER_DELETE_DIR = 'AfterDelete';
    public const AFTER_DELETE_COPY_PATH = 'data/module-manager-events';

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Manager $moduleManager,
        private readonly EntityManager $entityManager,
        private readonly UserContext $userContext,
        private readonly Connection $connection,
        private readonly Config $config
    ) {
    }

    /**
     * Runs the AfterInstall steps of the core and then of every module, in the load order of the modules.
     */
    public function afterInstallAll(): void
    {
        $this->afterInstall(self::CORE_ID);

        foreach ($this->moduleManager->getModulesList() as $moduleId) {
            $this->afterInstall($moduleId);
        }
    }

    public function afterInstall(string $moduleId): void
    {
        $dir = $this->getAppPath($moduleId) . self::AFTER_INSTALL_DIR;

        $classes = $this->findSteps($dir, $moduleId . '\\' . self::AFTER_INSTALL_DIR, AfterInstallInterface::class);
        if (empty($classes)) {
            return;
        }

        $this->setSystemUser();

        foreach ($classes as $className) {
            $this->container->get($className)->run();
        }
    }

    /**
     * Runs the AfterDelete steps of a module that is already removed, from their copy.
     */
    public function afterDelete(string $moduleId): void
    {
        $dir = self::AFTER_DELETE_COPY_PATH . '/' . $moduleId . '/' . self::AFTER_DELETE_DIR;

        foreach ($this->findSteps($dir, $moduleId . '\\' . self::AFTER_DELETE_DIR, AbstractAfterDelete::class) as $className) {
            (new $className($this->connection, $this->config))->run();
        }
    }

    /**
     * Copies the AfterDelete steps of every module, so that they exist after the module is removed.
     */
    public function copyAfterDeleteSteps(): void
    {
        foreach ($this->moduleManager->getModules() as $module) {
            $dest = self::AFTER_DELETE_COPY_PATH . '/' . $module->getId();

            $this->removeDir($dest);

            $src = $module->getAppPath() . self::AFTER_DELETE_DIR;
            if (!is_dir($src)) {
                continue;
            }

            $dest .= '/' . self::AFTER_DELETE_DIR;
            if (!is_dir($dest) && !mkdir($dest, 0777, true) && !is_dir($dest)) {
                throw new Error("Failed to create the folder '$dest'.");
            }

            foreach (glob($src . '/*.php') as $file) {
                if (!copy($file, $dest . '/' . basename($file))) {
                    throw new Error("Failed to copy '$file' to '$dest'.");
                }
            }
        }
    }

    /**
     * @return string[] the classes of the steps in the order they are run
     */
    private function findSteps(string $dir, string $namespace, string $parent): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $sortOrders = [];
        foreach (glob($dir . '/*.php') as $file) {
            $className = $namespace . '\\' . basename($file, '.php');
            if (!is_a($className, $parent, true)) {
                throw new Error("$className must be a subclass of $parent.");
            }

            $sortOrders[$className] = $className::getSortOrder();
        }

        asort($sortOrders);

        return array_keys($sortOrders);
    }

    private function getAppPath(string $moduleId): string
    {
        if ($moduleId === self::CORE_ID) {
            return CORE_PATH . '/' . self::CORE_ID . '/';
        }

        $module = $this->moduleManager->getModule($moduleId);
        if ($module === null) {
            throw new Error("Module '$moduleId' is not found.");
        }

        return $module->getAppPath();
    }

    /**
     * The steps are not run on behalf of a person, so the records they create are created by the system user.
     */
    private function setSystemUser(): void
    {
        if ($this->userContext->getUser() !== null) {
            return;
        }

        $user = $this->entityManager->getRepository('User')->getGlobalSystemUser();

        $this->entityManager->setUser($user);
        $this->userContext->set($user);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
