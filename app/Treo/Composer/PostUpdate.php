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

namespace Treo\Composer;

use Espo\Core\Container;
use Treo\Core\Application as App;

/**
 * Class PostUpdate
 */
class PostUpdate
{
    /**
     * @var Container
     */
    private static $container;

    /**
     * Run post-update actions
     */
    public function run(): void
    {
        // set container
        self::$container = (new App())->getContainer();

        // logout all
        self::logoutAll();

        // copy root files
        self::copyRootFiles();

        // save stable-composer.json file
        self::saveStableComposerJson();

        // update modules list
        self::updateModulesList();

        // copy modules event
        self::copyModulesEvent();

        // copy modules migrations
        self::copyModulesMigrations();

        // upload demo data if it needs
        self::uploadDemoData();

        // cache clearing
        self::clearCache();

        // update client files
        self::updateClientFiles();

        // create config if it needs
        self::createConfig();

        // init events
        self::initEvents();

        // run migrations
        self::runMigrations();

        //send notification
        self::sendNotification();
    }

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
     * @param string $message
     * @param bool   $break
     */
    public static function renderLine(string $message, bool $break = true)
    {
        $result = date('d.m.Y H:i:s') . ' | ' . $message;
        if ($break) {
            $result .= PHP_EOL;
        }

        echo $result;
    }

    /**
     * @param string $dir
     *
     * @return array
     */
    public static function scanDir(string $dir): array
    {
        // prepare result
        $result = [];

        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * Remove dir recursively
     *
     * @param string $dir
     *
     * @return void
     */
    public static function removeDir(string $dir): void
    {
        if (file_exists($dir) && is_dir($dir)) {
            foreach (self::scanDir($dir) as $object) {
                if (is_dir($dir . "/" . $object)) {
                    self::removeDir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Copy dir recursively
     *
     * @param string $src
     * @param string $dest
     *
     * @return void
     */
    public static function copyDir(string $src, string $dest): void
    {
        if (!is_dir($src)) {
            return;
        }

        if (!is_dir($dest)) {
            if (!mkdir($dest)) {
                return;
            }
        }

        $i = new \DirectoryIterator($src);
        foreach ($i as $f) {
            if ($f->isFile()) {
                copy($f->getRealPath(), "$dest/" . $f->getFilename());
            } else {
                if (!$f->isDot() && $f->isDir()) {
                    self::copyDir($f->getRealPath(), "$dest/$f");
                }
            }
        }
    }

    /**
     * @param string $dir
     */
    public static function createDir(string $dir): void
    {
        if (!file_exists($dir)) {
            try {
                mkdir($dir, 0777, true);
                sleep(1);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Logout all
     */
    private static function logoutAll(): void
    {
        if (!self::$container->get('config')->get('isInstalled')) {
            return;
        }

        self::renderLine('Logout all...');

        try {
            $sth = self::$container->get('pdo')->prepare("UPDATE auth_token SET deleted=1 WHERE 1");
            $sth->execute();
        } catch (\Throwable $e) {
            self::renderLine('Error!');
            exit(1);
        }

        self::renderLine('Done!');
    }

    /**
     * Copy root files
     */
    private static function copyRootFiles(): void
    {
        $src = dirname(dirname(dirname(__DIR__))) . '/copy';
        $dest = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));

        self::copyDir($src, $dest);
    }

    /**
     * Save stable-composer.json file
     */
    private static function saveStableComposerJson(): void
    {
        file_put_contents('data/stable-composer.json', file_get_contents('data/stable-composer.json'));
    }

    /**
     * Update modules list
     */
    private static function updateModulesList(): void
    {
        file_put_contents('data/modules.json', json_encode(self::getModules()));
    }

    /**
     * Copy modules event class
     */
    private static function copyModulesEvent(): void
    {
        foreach (self::getModules() as $module) {
            // prepare class name
            $className = "\\" . $module . "\\Event";

            if (class_exists($className)) {
                // get src
                $src = (new \ReflectionClass($className))->getFileName();

                if (!file_exists($src)) {
                    continue 1;
                }

                // prepare dest
                $dest = "data/module-manager-events/{$module}";

                // create dir
                self::createDir($dest);

                // prepare dest
                $dest .= "/Event.php";

                // delete old
                if (file_exists($dest)) {
                    unlink($dest);
                }

                // copy
                if (file_exists($src)) {
                    copy($src, $dest);
                }
            }
        }
    }

    /**
     * Copy modules migrations classes
     */
    private static function copyModulesMigrations(): void
    {
        // prepare data
        $data = [];

        // set treo migrations
        $data['Treo'] = 'vendor/atrocore/core/app/Treo/Migrations';

        foreach (self::getModules() as $id) {
            // prepare src
            $src = dirname((new \ReflectionClass("\\$id\\Module"))->getFileName()) . '/Migrations';

            if (file_exists($src) && is_dir($src)) {
                $data[$id] = $src;
            }
        }

        // copy
        foreach ($data as $id => $src) {
            // prepare dest
            $dest = "data/migrations/{$id}/Migrations";

            // create dir
            self::createDir($dest);

            // skip
            if (!file_exists($src) || !is_dir($src)) {
                continue 1;
            }

            foreach (scandir($src) as $file) {
                // skip
                if (in_array($file, ['.', '..'])) {
                    continue 1;
                }

                // delete old
                if (file_exists("$dest/$file")) {
                    unlink("$dest/$file");
                }

                // copy
                copy("$src/$file", "$dest/$file");
            }
        }
    }

    /**
     * Get installed modules
     *
     * @return array
     */
    private static function getModules(): array
    {
        $modules = [];

        foreach (self::getComposerLockPackages() as $row) {
            // prepare module name
            $moduleName = $row['extra']['treoId'];

            // prepare class name
            $className = "\\$moduleName\\Module";

            if (class_exists($className)) {
                $modules[$moduleName] = $className::getLoadOrder();
            }
        }
        asort($modules);

        return array_keys($modules);
    }

    /**
     * Get prepared composer.lock packages
     *
     * @param string $path
     *
     * @return array
     */
    private static function getComposerLockPackages(string $path = 'composer.lock'): array
    {
        // prepare result
        $result = [];

        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            if (!empty($packages = $data['packages'])) {
                foreach ($packages as $package) {
                    if (!empty($package['extra']['treoId'])) {
                        $result[$package['name']] = $package;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Upload demo data if it needs
     */
    private static function uploadDemoData()
    {
        if (file_exists('first_update.log')) {
            $content = @file_get_contents('https://demo-source.atropim.com/demo-data.zip');
            if (!empty($content)) {
                file_put_contents('demo-data.zip', $content);
                $zip = new \ZipArchive();
                if ($zip->open('demo-data.zip') === true) {
                    $zip->extractTo('.');
                    $zip->close();
                }
                unlink('demo-data.zip');

                // copy
                exec('cp -r skeleton-tmp/. .');

                // remove
                exec('rm -R skeleton-tmp');

                // unlink installing file
                unlink('first_update.log');
            }
        }
    }


    /**
     * Clear cache
     */
    private static function clearCache()
    {
        self::renderLine('Cache clearing...');

        try {
            self::removeDir('data/cache');
            self::createDir('data/cache');

            self::$container->get('config')->remove('cacheTimestamp');
            self::$container->get('config')->save();
        } catch (\Throwable $e) {
            self::renderLine('Error!');
            exit(1);
        }

        self::renderLine('Done!');
    }

    /**
     * Update client files
     */
    private static function updateClientFiles(): void
    {
        self::renderLine('Copy js files...');

        try {
            self::removeDir('client');
            self::copyDir(dirname(CORE_PATH) . '/client', 'client');
            foreach (self::$container->get('moduleManager')->getModules() as $module) {
                self::copyDir($module->getClientPath(), 'client');
            }
        } catch (\Throwable $e) {
            self::renderLine('Error!');
            exit(1);
        }

        self::renderLine('Done!');
    }

    /**
     * Create config
     */
    private static function createConfig(): void
    {
        // prepare config path
        $path = 'data/config.php';

        if (!file_exists($path)) {
            self::renderLine('Create main config...');

            try {
                // get default data
                $data = include 'vendor/atrocore/core/app/Espo/Core/defaults/config.php';

                // prepare salt
                $data['passwordSalt'] = mb_substr(md5((string)time()), 0, 9);

                // get content
                $content = "<?php\nreturn " . self::$container->get('fileManager')->varExport($data) . ";\n?>";

                // create config
                file_put_contents($path, $content);
            } catch (\Throwable $e) {
                self::renderLine('Error!');
                exit(1);
            }

            self::renderLine('Done!');
        }
    }

    /**
     * Init events
     */
    private static function initEvents(): void
    {
        if (!self::$container->get('config')->get('isInstalled')) {
            return;
        }

        // get diff
        $composerDiff = self::getComposerDiff();

        // call afterInstall event
        if (!empty($composerDiff['install'])) {
            // rebuild
            self::$container->get('dataManager')->rebuild();

            // run
            foreach ($composerDiff['install'] as $row) {
                self::renderLine('Call after install event for ' . $row['id'] . '... ');
                try {
                    self::$container->get('moduleManager')->getModuleInstallDeleteObject($row['id'])->afterInstall();
                } catch (\Throwable $e) {
                    self::renderLine('Error!');
                    exit(1);
                }

                self::renderLine('Done!');
            }
        }

        // call afterDelete event
        if (!empty($composerDiff['delete'])) {
            // run
            foreach ($composerDiff['delete'] as $row) {
                self::renderLine('Call after delete event for ' . $row['id'] . '... ');
                try {
                    self::$container->get('moduleManager')->getModuleInstallDeleteObject($row['id'])->afterDelete();
                } catch (\Throwable $e) {
                    self::renderLine('Error!');
                    exit(1);
                }

                self::renderLine('Done!');
            }
        }
    }

    /**
     * Run migrations
     */
    private static function runMigrations(): void
    {
        if (!self::$container->get('config')->get('isInstalled')) {
            return;
        }

        if (empty($data = self::getComposerDiff()['update'])) {
            return;
        }

        $migration = self::$container->get('migration');

        if (isset($data['Treo'])) {
            self::renderLine('Run migrations for Core... ');
            try {
                $migration->run('Treo', self::prepareVersion($data['Treo']['from']), self::prepareVersion($data['Treo']['to']));
            } catch (\Throwable $e) {
                self::renderLine('Error!');
                exit(1);
            }
            self::renderLine('Done!');

        }

        foreach (self::getModules() as $id) {
            if (isset($data[$id])) {
                self::renderLine('Run migrations for ' . $id . '... ');
                try {
                    $migration->run($id, self::prepareVersion($data[$id]['from']), self::prepareVersion($data[$id]['to']));
                } catch (\Throwable $e) {
                    self::renderLine('Error!');
                    exit(1);
                }
                self::renderLine('Done!');
            }
        }
    }

    /**
     * Send Notification Admin Users when updated composer
     */
    private static function sendNotification(): void
    {
        $composerDiff = self::getComposerDiff();

        if (!empty($composerDiff['install']) || !empty($composerDiff['update']) || !empty($composerDiff['delete'])) {
            self::renderLine('Send update notifications to admin users... ');
            try {
                $em = self::$container->get('entityManager');
                $users = $em->getRepository('User')->getAdminUsers();
                if (!empty($users)) {
                    foreach ($composerDiff as $status => $modules) {
                        foreach ($modules as $module) {
                            foreach ($users as $user) {
                                $notification = $em->getEntity('Notification');
                                $notification->set('type', 'Message');
                                $notification->set('message', self::getMessageForComposer($status, $module));
                                $notification->set('userId', $user['id']);
                                $em->saveEntity($notification);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                self::renderLine('Error!');
                exit(1);
            }

            self::renderLine('Done!');
        }
    }

    /**
     * @param string $status
     * @param array  $module
     *
     * @return string
     */
    private static function getMessageForComposer(string $status, array $module): string
    {
        $language = self::$container->get('language');

        if ($module['id'] != 'Treo') {
            $nameModule = !empty($module["package"]["extra"]["name"]["default"]) ? $module["package"]["extra"]["name"]["default"] : $module['id'];
        } else {
            $nameModule = 'System';
        }

        if ($status === 'update') {
            if (version_compare($module['to'], $module['from'], '>=')) {
                $keyLang = $nameModule == 'System' ? 'System update' : 'Module update';
            } else {
                $keyLang = $nameModule == 'System' ? 'System downgrade' : 'Module downgrade';
            }

            $message = $language->translate($keyLang, 'notifications', 'Composer');
            $message = str_replace('{module}', $nameModule, $message);
            $message = str_replace('{from}', $module['from'], $message);
            $message = str_replace('{to}', $module['to'], $message);
        } else {
            $message = $language->translate("Module {$status}", 'notifications', 'Composer');
            $message = str_replace('{module}', $nameModule, $message);
            if (isset($module["package"]["version"])) {
                $message = str_replace('{version}', $module["package"]["version"], $message);
            }
        }

        return $message;
    }

    /**
     * Get composer diff
     *
     * @return array
     */
    private static function getComposerDiff(): array
    {
        // prepare result
        $result = [
            'install' => [],
            'update'  => [],
            'delete'  => [],
        ];

        // parse packages
        $packages = self::getComposerLockPackages();

        // get diff path
        $diffPath = 'data/composer-diff';

        foreach (self::scanDir($diffPath) as $type) {
            foreach (self::scanDir("$diffPath/$type") as $file) {
                $parts = explode('_', file_get_contents("$diffPath/$type/$file"));
                $moduleId = str_replace('.txt', '', $file);
                $result[$type][$moduleId] = [
                    'id'      => $moduleId,
                    'package' => (isset($packages[$parts[0]])) ? $packages[$parts[0]] : null,
                    'from'    => (isset($parts[1])) ? $parts[1] : null,
                    'to'      => (isset($parts[2])) ? $parts[2] : null
                ];
            }
        }

        return $result;
    }
}
