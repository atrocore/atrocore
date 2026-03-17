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

namespace Atro\Core\Routing;

use Atro\Core\DataManager;
use Atro\Core\ModuleManager\Manager as ModuleManager;

class HandlerRegistry
{
    public function __construct(
        private readonly DataManager   $dataManager,
        private readonly ModuleManager $moduleManager,
    ) {
    }

    public function getHandlerClasses(): array
    {
        $cached = $this->dataManager->getCacheData('handler_routes');
        if ($cached !== null) {
            return $cached;
        }

        $classes  = [];
        $coreBase = CORE_PATH . '/';

        foreach ($this->scanFiles(CORE_PATH . '/Atro/Handlers/') as $file) {
            $relative  = substr($file, strlen($coreBase));
            $classes[] = str_replace('/', '\\', substr($relative, 0, -4));
        }

        foreach ($this->moduleManager->getModules() as $module) {
            $classes = array_merge($classes, $module->getHandlerClasses());
        }

        $this->dataManager->setCacheData('handler_routes', $classes);

        return $classes;
    }

    private function scanFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
