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

namespace Espo\Core\Migration;

use Espo\Core\Injectable;
use Atro\Core\Migration\Base;

class Migration extends Injectable
{
    public function __construct()
    {
        $this->addDependency('config');
        $this->addDependency('pdo');
        $this->addDependency('schema');
    }

    /**
     * Migrate action
     *
     * @param string $module
     * @param string $from
     * @param string $to
     *
     * @return bool
     */
    public function run(string $module, string $from, string $to): bool
    {
        // get module migration versions
        if (empty($migrations = $this->getModuleMigrationVersions($module))) {
            return false;
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
            return false;
        }

        // prepare increment
        if ($keyFrom < $keyTo) {
            // go UP
            foreach ($data as $k => $className) {
                if ($k >= $keyFrom
                    && $keyTo >= $k
                    && $from != $className
                    && in_array($className, $migrations)
                    && !empty($migration = $this->createMigration($module, $className))) {
                    self::renderLine("Run migration " . str_replace(['V', 'Dot'], ['', '.'], $className));
                    $migration->up();
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
                    self::renderLine("Run migration " . str_replace(['V', 'Dot'], ['', '.'], $className));
                    $migration->down();
                }
            }
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

        return new $className($this->getInjection('pdo'), $this->getInjection('config'), $this->getInjection('schema'));
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
