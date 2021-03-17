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
    private const COMPOSER_LOG_FILE = 'data/treo-composer.log';
    private const CONFIG_PATH = 'data/config.php';
    private const STABLE_COMPOSER_JSON = 'data/stable-composer.json';
    private const PREVIOUS_COMPOSER_LOCK = 'data/previous-composer.lock';
    private const DUMP_DIR = 'dump';
    private const DB_DUMP = self::DUMP_DIR . '/db.sql';
    private const DIRS_FOR_DUMPING = ['data', 'client', 'custom', 'vendor'];

    /**
     * @var Container
     */
    private static $container;

    /**
     * @var string
     */
    private static $rootPath;

    /**
     * Restore force
     *
     * @param bool $autoRestore
     */
    public static function restoreForce(bool $autoRestore = false): void
    {
        try {
            // get root path
            self::$rootPath = self::getRootPath();
        } catch (\Throwable $e) {
            self::renderLine($e->getMessage());
            exit(1);
        }

        // change directory
        chdir(self::$rootPath);

        // set the include_path
        set_include_path(self::$rootPath);

        if (!$autoRestore && file_exists(self::COMPOSER_LOG_FILE)) {
            unlink(self::COMPOSER_LOG_FILE);
        }

        $exitCode = $autoRestore ? 1 : 0;

        if (!file_exists(self::DUMP_DIR)) {
            self::renderLine('Restoring failed! No dump data!');
            exit(1);
        }

        self::renderLine('Restoring files...', false);
        foreach (self::DIRS_FOR_DUMPING as $dir) {
            $path = self::DUMP_DIR . '/' . $dir;
            if (!file_exists($path)) {
                continue 1;
            }
            exec("cp -R {$path}/ .");
        }
        file_put_contents('composer.lock', file_get_contents(self::PREVIOUS_COMPOSER_LOCK));
        self::renderLine(' Done!');

        self::renderLine('Restoring database...', false);

        if (!file_exists(self::DB_DUMP) || filesize(self::DB_DUMP) == 0) {
            self::renderLine(' No database dump found!');
            exit($exitCode);
        }

        if (!file_exists(self::CONFIG_PATH)) {
            self::renderLine(' Failed!');
            exit(1);
        }
        $config = include self::CONFIG_PATH;
        if (empty($config['database'])) {
            self::renderLine(' Failed!');
            exit(1);
        }
        $db = $config['database'];
        $port = empty($db['port']) ? '' : "port={$db['port']};";
        $options = [];
        if (isset($db['sslCA'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = $db['sslCA'];
        }
        if (isset($db['sslCert'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CERT] = $db['sslCert'];
        }
        if (isset($db['sslKey'])) {
            $options[\PDO::MYSQL_ATTR_SSL_KEY] = $db['sslKey'];
        }
        if (isset($db['sslCAPath'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CAPATH] = $db['sslCAPath'];
        }
        if (isset($db['sslCipher'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CIPHER] = $db['sslCipher'];
        }
        $pdo = new \PDO("mysql:host={$db['host']};{$port}dbname={$db['dbname']};charset={$db['charset']}", $db['user'], $db['password'], $options);
        $pdo->exec(file_get_contents(self::DB_DUMP));
        self::renderLine(' Done!');

        exit($exitCode);
    }

    /**
     * Restore
     *
     * @throws \Exception
     */
    public static function restore(): void
    {
        if (file_exists(self::COMPOSER_LOG_FILE)) {
            self::renderLine('System is updating. Restoring blocked.');
            exit(1);
        }

        self::restoreForce();
    }

    /**
     * Run post-update actions
     */
    public static function postUpdate()
    {
        try {
            // get root path
            self::$rootPath = self::getRootPath();
        } catch (\Throwable $e) {
            self::renderLine('Failed! ' . $e->getMessage());
            exit(1);
        }

        // change directory
        chdir(self::$rootPath);

        // set the include_path
        set_include_path(self::$rootPath);

        if (!self::isChanged()) {
            exit(0);
        }

        // autoload
        require_once 'vendor/autoload.php';

        // set container
        self::$container = (new App())->getContainer();

        try {
            // logout all
            self::logoutAll();

            // create dump for database and files
            self::createDump();

            // copy root files
            self::copyRootFiles();

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

            // create config if it needs
            self::createConfig();

            // update client files
            self::updateClientFiles();

            // init events
            self::initEvents();

            // run migrations
            self::runMigrations();

            // send notification
            self::sendNotification();

            self::onSuccess();
        } catch (\Throwable $e) {
            self::renderLine(' Failed! ' . $e->getMessage());
            self::restoreForce(true);
            exit(1);
        }
    }

    /**
     * PostUpdate constructor.
     */
    public function __construct()
    {
    }

    /**
     * @deprecated will be removed after 01.01.2022
     */
    public function run(): void
    {
        self::postUpdate();
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
        if (!self::$container->get('config')->get('isInstalled', false)) {
            return;
        }

        self::renderLine('Logging out all users...', false);
        self::$container->get('pdo')->exec("DELETE FROM auth_token WHERE 1");
        self::renderLine(' Done!');
    }

    /**
     * Copy root files
     */
    private static function copyRootFiles(): void
    {
        if (self::$container->get('config')->get('isInstalled', false)) {
            return;
        }

        self::renderLine('Coping system files...', false);
        self::copyDir(self::$rootPath . '/vendor/atrocore/core/copy', self::$rootPath);
        self::renderLine(' Done!');
    }

    /**
     * Update modules list
     */
    private static function updateModulesList(): void
    {
        self::renderLine('Updating list of used modules...', false);
        file_put_contents('data/modules.json', json_encode(self::getModules()));
        self::renderLine(' Done!');
    }

    /**
     * Copy modules event class
     */
    private static function copyModulesEvent(): void
    {
        self::renderLine('Coping post-install & post-delete scripts for modules...', false);
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
        self::renderLine(' Done!');
    }

    /**
     * Copy modules migrations classes
     */
    private static function copyModulesMigrations(): void
    {
        self::renderLine('Coping migration scripts...', false);

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

        self::renderLine(' Done!');
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
            self::renderLine('Uploading demo-data...', false);
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
                self::removeDir('skeleton-tmp');

                // unlink installing file
                unlink('first_update.log');
            }
            self::renderLine(' Done!');
        }
    }


    /**
     * Clear cache
     */
    private static function clearCache()
    {
        if (!self::$container->get('config')->get('isInstalled', false)) {
            return;
        }

        self::renderLine('Clearing cache...', false);

        self::removeDir('data/cache');
        self::createDir('data/cache');

        self::$container->get('config')->remove('cacheTimestamp');
        self::$container->get('config')->save();

        self::renderLine(' Done!');
    }

    /**
     * Update client files
     */
    private static function updateClientFiles(): void
    {
        self::renderLine('Coping frontend files...', false);

        self::removeDir('client');
        self::copyDir(dirname(CORE_PATH) . '/client', 'client');
        foreach (self::$container->get('moduleManager')->getModules() as $module) {
            self::copyDir($module->getClientPath(), 'client');
        }

        self::renderLine(' Done!');
    }

    /**
     * Create config
     */
    private static function createConfig(): void
    {
        // prepare config path
        $path = self::CONFIG_PATH;

        if (!file_exists($path)) {
            self::renderLine('Creating main config...', false);

            // get default data
            $data = include 'vendor/atrocore/core/app/Espo/Core/defaults/config.php';

            // prepare salt
            $data['passwordSalt'] = mb_substr(md5((string)time()), 0, 9);

            // get content
            $content = "<?php\nreturn " . self::$container->get('fileManager')->varExport($data) . ";\n?>";

            // create config
            file_put_contents($path, $content);

            self::renderLine(' Done!');
        }
    }

    /**
     * Init events
     */
    private static function initEvents(): void
    {
        if (!self::$container->get('config')->get('isInstalled', false)) {
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
                self::renderLine('Calling post-install script for ' . $row['id'] . '...', false);
                self::$container->get('moduleManager')->getModuleInstallDeleteObject($row['id'])->afterInstall();
                self::renderLine(' Done!');
            }
        }

        // call afterDelete event
        if (!empty($composerDiff['delete'])) {
            // run
            foreach ($composerDiff['delete'] as $row) {
                self::renderLine('Calling post-delete script for ' . $row['id'] . '...', false);
                self::$container->get('moduleManager')->getModuleInstallDeleteObject($row['id'])->afterDelete();
                self::renderLine(' Done!');
            }
        }
    }

    /**
     * Run migrations
     */
    private static function runMigrations(): void
    {
        if (!self::$container->get('config')->get('isInstalled', false)) {
            return;
        }

        if (empty($data = self::getComposerDiff()['update'])) {
            return;
        }

        $migration = self::$container->get('migration');

        if (isset($data['Treo'])) {
            self::renderLine('Running migrations for Core...', false);
            $migration->run('Treo', self::prepareVersion($data['Treo']['from']), self::prepareVersion($data['Treo']['to']));
            self::renderLine(' Done!');

        }

        foreach (self::getModules() as $id) {
            if (isset($data[$id])) {
                self::renderLine('Running migrations for ' . $id . '...', false);
                $migration->run($id, self::prepareVersion($data[$id]['from']), self::prepareVersion($data[$id]['to']));
                self::renderLine(' Done!');
            }
        }
    }

    /**
     * Send Notification Admin Users when updated composer
     */
    private static function sendNotification(): void
    {
        self::renderLine('Sending notification(s) to admin users...', false);
        $em = self::$container->get('entityManager');
        $users = $em->getRepository('User')->getAdminUsers();
        if (!empty($users)) {
            foreach (self::getComposerDiff() as $status => $modules) {
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

        self::renderLine(' Done!');
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
        if (!file_exists(self::PREVIOUS_COMPOSER_LOCK) && file_exists('composer-cmd.php')) {
            return self::getComposerDiffByFiles();
        }

        // prepare result
        $result = [
            'install' => [],
            'update'  => [],
            'delete'  => [],
        ];

        if (!file_exists(self::PREVIOUS_COMPOSER_LOCK)) {
            return $result;
        }

        // prepare data
        $oldData = self::getComposerLockPackages(self::PREVIOUS_COMPOSER_LOCK);
        $newData = self::getComposerLockPackages();

        foreach ($oldData as $package) {
            if (!isset($newData[$package['name']])) {
                $result['delete'][$package['extra']['treoId']] = [
                    'id'      => $package['extra']['treoId'],
                    'package' => $package,
                    'from'    => null,
                    'to'      => null
                ];
            } elseif ($package['version'] != $newData[$package['name']]['version']) {
                $result['update'][$package['extra']['treoId']] = [
                    'id'      => $package['extra']['treoId'],
                    'package' => $newData[$package['name']],
                    'from'    => $package['version'],
                    'to'      => $newData[$package['name']]['version']
                ];
            }
        }
        foreach ($newData as $package) {
            if (!isset($oldData[$package['name']])) {
                $result['install'][$package['extra']['treoId']] = [
                    'id'      => $package['extra']['treoId'],
                    'package' => $package,
                    'from'    => null,
                    'to'      => null
                ];
            }
        }

        return $result;
    }

    /**
     * @deprecated will be removed after 01.01.2022
     */
    private static function getComposerDiffByFiles(): array
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

    /**
     * @return string
     * @throws \Exception
     */
    private static function getRootPath(): string
    {
        $rootPath = '';

        $path = __FILE__;
        while (empty($rootPath)) {
            $path = dirname($path);
            if (file_exists($path . "/composer.phar")) {
                $rootPath = $path;
            }

            if ($path == '/') {
                throw new \Exception("Can't find root directory.");
            }
        }

        return $rootPath;
    }

    /**
     * Update successful
     */
    private static function onSuccess(): void
    {
        if (file_exists('composer.lock')) {
            file_put_contents(self::PREVIOUS_COMPOSER_LOCK, file_get_contents('composer.lock'));
        }

        if (file_exists('composer.json')) {
            file_put_contents(self::STABLE_COMPOSER_JSON, file_get_contents('composer.json'));
        }

        exit(0);
    }

    /**
     * @throws \Throwable
     */
    private static function createDump(): void
    {
        if (!self::$container->get('config')->get('isInstalled', false)) {
            return;
        }

        self::createDir(self::DUMP_DIR);

        self::renderLine('Creating restoring point...', false);

        $ignore = !file_exists(self::DUMP_DIR . '/vendor');

        // copy files
        foreach (self::DIRS_FOR_DUMPING as $dir) {
            if ($dir == 'vendor') {
                continue 1;
            }
            self::removeDir(self::DUMP_DIR . '/' . $dir);
            exec('cp -R ' . $dir . '/ ' . self::DUMP_DIR . '/' . $dir . ' 2>/dev/null', $output, $result);
            if (!empty($result)) {
                $message = 'Please, configure files permissions!';
                if ($ignore) {
                    self::renderLine(' Failed! ' . $message);
                    $isFailed = true;
                    break 1;
                } else {
                    throw new \Exception($message);
                }
            }
        }
        // remove composer log file from dump
        if (file_exists(self::DUMP_DIR . '/' . self::COMPOSER_LOG_FILE)) {
            unlink(self::DUMP_DIR . '/' . self::COMPOSER_LOG_FILE);
        }

        // mysqldump
        $db = self::$container->get('config')->get('database');
        $mysqldump = "mysqldump -h {$db['host']} -u {$db['user']} -p{$db['password']} {$db['dbname']} > " . self::DB_DUMP;
        exec($mysqldump . ' 2>/dev/null', $output, $result);
        if (!empty($result)) {
            $message = "Please, install mysqldump! System can't create dump for database!";
            if ($ignore) {
                self::renderLine(' Failed! ' . $message);
                $isFailed = true;
            } else {
                throw new \Exception($message);
            }
        }

        if (empty($isFailed)) {
            self::renderLine(' Done!');
        }
    }

    private static function isChanged(): bool
    {
        $composerDiff = self::getComposerDiff();

        return !empty($composerDiff['install']) || !empty($composerDiff['update']) || !empty($composerDiff['delete']);
    }
}
