<?php
/**
 * This file is part of EspoCRM and/or TreoCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * TreoCore is EspoCRM-based Open Source application.
 * Copyright (C) 2017-2019 TreoLabs GmbH
 * Website: https://treolabs.com
 *
 * TreoCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TreoCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
 * and "TreoCore" word.
 */

declare(strict_types=1);

use Treo\Composer\PostUpdate;
use Treo\Core\Utils\Util;

/**
 * Class ComposerCmd
 *
 * @author r.ratsun@treolabs.com
 */
class ComposerCmd
{
    const DIFF_PATH = 'data/composer-diff';

    /**
     * Before update
     */
    public static function preUpdate(): void
    {
        if (class_exists(Util::class)) {
            // delete diff cache
            Util::removeDir(self::DIFF_PATH);
        }
    }

    /**
     * After update
     */
    public static function postUpdate(): void
    {
        // change directory
        chdir(dirname(__FILE__));

        // set the include_path
        set_include_path(dirname(__FILE__));

        // autoload
        require_once 'vendor/autoload.php';

        // run post update actions
        (new PostUpdate())->run();
    }

    /**
     * After package install
     *
     * @param mixed $event
     *
     * @return void
     */
    public static function postPackageInstall($event): void
    {
        try {
            $name = $event->getOperation()->getPackage()->getName();
        } catch (\Throwable $e) {
        }

        if (isset($name)) {
            self::createPackageActionFile($name, 'install');
        }
    }

    /**
     * @param mixed $event
     *
     * @return void
     */
    public static function postPackageUpdate($event): void
    {
        // get composer update pretty line
        $prettyLine = (string)$event->getOperation();

        preg_match_all("/^Updating (.*) \((.*)\) to (.*) \((.*)\)$/", $prettyLine, $matches);
        if (count($matches) == 5) {
            self::createPackageActionFile($matches[1][0], 'update', $matches[2][0] . '_' . $matches[4][0]);
        }
    }

    /**
     * Before package uninstall
     *
     * @param mixed $event
     *
     * @return void
     */
    public static function prePackageUninstall($event): void
    {
        try {
            $name = $event->getOperation()->getPackage()->getName();
        } catch (\Throwable $e) {
        }

        if (isset($name)) {
            self::createPackageActionFile($name, 'delete');
        }
    }

    /**
     * @param string $name
     * @param string $dir
     * @param string $content
     *
     * @return bool
     */
    protected static function createPackageActionFile(string $name, string $dir, string $content = ''): bool
    {
        // find composer.json file
        $file = "vendor/$name/composer.json";
        if (!file_exists($file)) {
            return false;
        }

        // try to parse composer.json file
        try {
            $data = json_decode(file_get_contents($file), true);
        } catch (\Throwable $e) {
            return false;
        }

        // exit if is not treo package
        if (!isset($data['extra']['treoId'])) {
            return false;
        }

        // prepare dir path
        $dirPath = self::DIFF_PATH . "/$dir";

        // create dir if it needs
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        // prepare content
        $content = (empty($content)) ? $name : $name . '_' . $content;

        // save
        file_put_contents("$dirPath/{$data['extra']['treoId']}.txt", $content);

        return true;
    }
}
