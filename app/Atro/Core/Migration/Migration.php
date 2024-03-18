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

namespace Atro\Core\Migration;

use Atro\Core\Container;

class Migration
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getMigrationsToExecute(string $module, string $from, string $to): array
    {
        $migrations = $this->getModuleMigrationVersions($module);
        if (empty($migrations)) {
            return [];
        }

        // prepare versions
        $from = $this->prepareVersion($from);
        $to = $this->prepareVersion($to);

        // prepare data
        $data = $migrations;
        $data[] = $from;
        $data[] = $to;
        $data = array_unique($data);

        // sort
        natsort($data);

        $data = array_values($data);

        // prepare keys
        $keyFrom = array_search($from, $data);
        $keyTo = array_search($to, $data);

        if ($keyFrom == $keyTo) {
            return [];
        }

        $res = [];

        if ($keyFrom < $keyTo) {
            // go UP
            foreach ($data as $k => $className) {
                if ($k >= $keyFrom
                    && $keyTo >= $k
                    && $from != $className
                    && in_array($className, $migrations)
                    && !empty($migration = $this->createMigration($module, $className))) {
                    $res[] = [
                        'moduleId'  => $module,
                        'method'    => 'up',
                        'version'   => str_replace(['V', 'Dot'], ['', '.'], $className),
                        'migration' => $migration,
                    ];
                }
            }
        } else {
            // go DOWN
            foreach (array_reverse($data, true) as $k => $className) {
                if ($k >= $keyTo
                    && $keyFrom >= $k
                    && $to != $className
                    && in_array($className, $migrations)
                    && !empty($migration = $this->createMigration($module, $className))) {
                    $res[] = [
                        'moduleId'  => $module,
                        'method'    => 'down',
                        'version'   => str_replace(['V', 'Dot'], ['', '.'], $className),
                        'migration' => $migration
                    ];
                }
            }
        }

        return $res;
    }

    public function run(string $module, string $from, string $to): bool
    {
        $res = $this->getMigrationsToExecute($module, $from, $to);
        foreach ($res as $row) {
            self::renderLine("Run migration {$row['moduleId']} {$row['version']}");
            $row['migration']->{$row['method']}();
        }

        return true;
    }

    /**
     * Prepare version
     *
     * @param string $version
     *
     * @return string|null
     */
    protected function prepareVersion(string $version): ?string
    {
        // prepare version
        $version = str_replace('v', '', $version);

        if (preg_match_all('/^(.*)\.(.*)\.(.*)$/', $version, $matches)) {
            // prepare data
            $major = (int)$matches[1][0];
            $version = (int)$matches[2][0];
            $patch = (int)$matches[3][0];

            return "V{$major}Dot{$version}Dot{$patch}";
        }

        return null;
    }

    /**
     * Get module migration versions
     *
     * @param string $module
     *
     * @return array
     */
    protected function getModuleMigrationVersions(string $module): array
    {
        // prepare result
        $result = [];

        // prepare path
        $path = sprintf('data/migrations/%s/Migrations/', $module);

        if (file_exists($path) && is_dir($path)) {
            foreach (scandir($path) as $file) {
                // prepare file name
                $file = str_replace('.php', '', $file);
                if (preg_match('/^V(.*)Dot(.*)Dot(.*)$/', $file)) {
                    $result[] = $file;
                }
            }
        }

        return $result;
    }

    /**
     * @param string $module
     * @param string $className
     *
     * @return null|Base
     */
    protected function createMigration(string $module, string $className): ?Base
    {
        // prepare class name
        $className = sprintf('\\%s\\Migrations\\%s', $module, $className);

        if (!class_exists($className) || !is_a($className, Base::class, true)) {
            return null;
        }

        return new $className($this->container->get('pdo'), $this->container->get('config'), $this->container->get('schema'));
    }

    /**
     * @param string $message
     * @param bool   $break
     */
    private static function renderLine(string $message, bool $break = true)
    {
        $result = date('d.m.Y H:i:s') . ' | ' . $message;
        if ($break) {
            $result .= PHP_EOL;
        }

        echo $result;
    }
}
