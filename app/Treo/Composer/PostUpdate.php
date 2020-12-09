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

use Treo\Core\Application as App;
use Treo\Core\Container;
use Treo\Core\Migration\Migration;
use Treo\Core\ModuleManager\Manager as ModuleManager;
use Treo\Core\ORM\EntityManager;
use Treo\Core\Utils\Config;
use Treo\Core\Utils\Util;
use Treo\Core\ModuleManager\AbstractEvent;
use Treo\Services\Composer as ComposerService;

/**
 * Class PostUpdate
 */
class PostUpdate
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var bool
     */
    private $byLockFile = false;

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
     * PostUpdate constructor.
     *
     * @param bool $byLockFile
     */
    public function __construct(bool $byLockFile = false)
    {
        // define path to core app
        if (!defined('CORE_PATH')) {
            define('CORE_PATH', dirname(dirname(__DIR__)));
        }

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

        // set container
        $this->container = (new App())->getContainer();

        // find diff by lock file ?
        $this->byLockFile = $byLockFile;
    }

    /**
     * Run
     */
    public function run(): void
    {
        // cache clearing
        $this->clearCache();

        // logout all users
        if ($this->isInstalled()) {
            $this->logoutAll();
        }

        // update client files
        $this->updateClientFiles();

        // copy default config if it needs
        $this->copyDefaultConfig();

        // init events
        $this->initEvents();

        if ($this->isInstalled()) {
            // run migrations
            $this->runMigrations();

            //send notification
            $this->sendNotification();
        }

        // store composer.lock file
        if ($this->byLockFile) {
            file_put_contents('data/old-composer.lock', file_get_contents(ComposerService::$composerLock));
        }
    }

    /**
     * Logout all
     */
    protected function logoutAll(): void
    {
        self::renderLine('Logout all...');

        $sth = $this
            ->getContainer()
            ->get('entityManager')
            ->getPDO()->prepare("UPDATE auth_token SET deleted = 1");

        $sth->execute();

        self::renderLine('Done!');
    }

    /**
     * Run migrations
     *
     * @return bool
     */
    protected function runMigrations(): bool
    {
        /** @var array $data */
        if (empty($data = $this->getComposerDiff()['update'])) {
            return false;
        }

        /** @var Migration $migration */
        $migration = $this->getContainer()->get('migration');

        if (isset($data['Treo'])) {
            $migration->run('Treo', ModuleManager::prepareVersion($data['Treo']['from']), ModuleManager::prepareVersion($data['Treo']['to']));
        }

        foreach (self::getModules() as $id) {
            if (isset($data[$id])) {
                $migration->run($id, ModuleManager::prepareVersion($data[$id]['from']), ModuleManager::prepareVersion($data[$id]['to']));
            }
        }

        return true;
    }

    /**
     * Get composer diff
     *
     * @return array
     */
    protected function getComposerDiff(): array
    {
        if ($this->byLockFile) {
            return $this->getComposerLockDiff();
        }

        // prepare result
        $result = [
            'install' => [],
            'update'  => [],
            'delete'  => [],
        ];

        // parse packages
        $packages = self::getComposerLockTreoPackages(ComposerService::$composerLock);

        // get diff path
        $diffPath = 'data/composer-diff';

        foreach (Util::scanDir($diffPath) as $type) {
            foreach (Util::scanDir("$diffPath/$type") as $file) {
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
     * Get composer.lock diff
     *
     * @return array
     */
    protected function getComposerLockDiff(): array
    {
        // prepare result
        $result = [
            'install' => [],
            'update'  => [],
            'delete'  => [],
        ];

        if (!file_exists('data/old-composer.lock')) {
            return $result;
        }

        // prepare data
        $oldData = self::getComposerLockTreoPackages('data/old-composer.lock');
        $newData = self::getComposerLockTreoPackages(ComposerService::$composerLock);

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
     * Get prepared composer.lock treo packages
     *
     * @param string $path
     *
     * @return array
     */
    protected static function getComposerLockTreoPackages(string $path): array
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
     * @return bool
     */
    protected function isInstalled(): bool
    {
        return !empty($this->getContainer()->get('config')->get('isInstalled'));
    }

    /**
     * Init events
     */
    protected function initEvents(): void
    {
        // get diff
        $composerDiff = $this->getComposerDiff();

        // call afterInstall event
        if (!empty($composerDiff['install'])) {
            // rebuild
            if ($this->isInstalled()) {
                $this->getContainer()->get('dataManager')->rebuild();
            }

            // run
            foreach ($composerDiff['install'] as $row) {
                self::renderLine('Call after install event for ' . $row['id'] . '... ');
                $this->callEvent($row['id'], 'afterInstall');
                self::renderLine('Done!');
            }
        }

        // call afterDelete event
        if (!empty($composerDiff['delete'])) {
            // run
            foreach ($composerDiff['delete'] as $row) {
                self::renderLine('Call after delete event for ' . $row['id'] . '... ');
                $this->callEvent($row['id'], 'afterDelete');
                self::renderLine('Done!');
            }
        }
    }

    /**
     * @param string $module
     * @param string $action
     */
    protected function callEvent(string $module, string $action): void
    {
        // prepare class name
        $className = '\\%s\\Event';

        $class = sprintf($className, $module);
        if (class_exists($class)) {
            $class = new $class();
            if ($class instanceof AbstractEvent) {
                $class->setContainer($this->getContainer())->{$action}();
            }
        }
    }

    /**
     * Send Notification Admin Users when updated composer
     */
    protected function sendNotification(): void
    {
        $composerDiff = $this->getComposerDiff();

        if (!empty($composerDiff['install']) || !empty($composerDiff['update']) || !empty($composerDiff['delete'])) {
            self::renderLine('Send update notifications to admin users... ');

            /** @var EntityManager $em */
            $em = $this
                ->getContainer()
                ->get('entityManager');
            $users = $em->getRepository('User')->getAdminUsers();
            if (!empty($users)) {
                foreach ($composerDiff as $status => $modules) {
                    foreach ($modules as $module) {
                        foreach ($users as $user) {
                            $message = $this->getMessageForComposer($status, $module);
                            // create notification
                            $notification = $em->getEntity('Notification');
                            $notification->set('type', 'Message');
                            $notification->set('message', $message);
                            $notification->set('userId', $user['id']);
                            // save notification
                            $em->saveEntity($notification);
                        }
                    }
                }
            }
            self::renderLine('Done!');
        }
    }

    /**
     * Clear cache
     */
    protected function clearCache()
    {
        if (empty($config = $this->getConfig())) {
            return;
        }

        self::renderLine('Cache clearing...');

        // clear cache
        $this->getContainer()->get('dataManager')->clearCache();

        self::renderLine('Done!');
    }

    /**
     * @return Config
     */
    protected function getConfig(): ?Config
    {
        if (!file_exists('data/config.php')) {
            return null;
        }

        return $this->getContainer()->get('config');
    }

    /**
     * Get container
     *
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @param string $status
     * @param array  $module
     *
     * @return string
     */
    private function getMessageForComposer(string $status, array $module): string
    {
        $language = $this->getContainer()->get('language');

        if ($module['id'] != 'Treo') {
            $nameModule = !empty($module["package"]["extra"]["name"]["default"])
                ? $module["package"]["extra"]["name"]["default"]
                : $module['id'];
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
     * Get installed modules
     *
     * @return array
     */
    private static function getModules(): array
    {
        $modules = [];
        foreach (self::getComposerLockTreoPackages(ComposerService::$composerLock) as $row) {
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
     * Save stable-composer.json file
     */
    private static function saveStableComposerJson(): void
    {
        file_put_contents(ComposerService::$stableComposer, file_get_contents(ComposerService::$composer));
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
                if (!file_exists($dest)) {
                    mkdir($dest, 0777, true);
                }

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
        $data['Treo'] = CORE_PATH . '/Treo/Migrations';

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
            if (!file_exists($dest)) {
                mkdir($dest, 0777, true);
            }

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
     * Copy root files
     */
    private static function copyRootFiles(): void
    {
        if (!file_exists('index.php')) {
            // prepare pathes
            $src = dirname(dirname(dirname(__DIR__))) . '/copy';
            $dest = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));

            Util::copyDir($src, $dest);
        }
    }

    /**
     * Update client files
     */
    private function updateClientFiles(): void
    {
        // delete old
        Util::removeDir('client');

        // copy new
        Util::copyDir(dirname(CORE_PATH) . '/client', 'client');
        foreach ($this->getContainer()->get('moduleManager')->getModules() as $module) {
            Util::copyDir($module->getClientPath(), 'client');
        }
    }

    /**
     * Copy default config
     */
    private function copyDefaultConfig(): void
    {
        // prepare config path
        $path = 'data/config.php';

        if (!file_exists($path)) {
            // get default data
            $data = include CORE_PATH . '/Treo/Configs/defaultConfig.php';

            // prepare salt
            $data['passwordSalt'] = mb_substr(md5((string)time()), 0, 9);

            // get content
            $content = "<?php\nreturn " . $this->getContainer()->get('fileManager')->varExport($data) . ";\n?>";

            // create config
            file_put_contents($path, $content);
        }
    }
}
