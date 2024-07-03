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

namespace Atro\Composer;

use Atro\Core\Application;
use Atro\Core\Container;
use Atro\Core\Application as App;
use Atro\Services\MassDownload;
use Espo\Core\Utils\Language;
use Espo\ORM\EntityManager;

class PostUpdate
{
    public const PDF_IMAGE_DIR = 'data/img-from-pdf';
    private const CONFIG_PATH = 'data/config.php';
    private const STABLE_COMPOSER_JSON = 'data/stable-composer.json';
    private const PREVIOUS_COMPOSER_LOCK = 'data/previous-composer.lock';

    /**
     * @var Container
     */
    private static $container;

    /**
     * @var string
     */
    private static $rootPath;

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

        // autoload
        require_once 'vendor/autoload.php';

        // set container
        self::$container = (new App())->getContainer();

        try {
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

            // run migrations
            self::runMigrations();

            // init events
            self::initEvents();

            // regenerate measures
            self::regenerateMeasures();

            // regenerate lists
            self::regenerateLists();

            // regenerate ui handlers
            self::regenerateUiHandlers();

            // refresh translations
            self::refreshTranslations();

            // send notification
            self::sendNotification();

            self::onSuccess();
        } catch (\Throwable $e) {
            $message = "Failed! {$e->getMessage()}";
            $trace = $e->getTrace();
            if (!empty($trace[0])) {
                $message .= ' ' . json_encode($trace[0]);
            }

            self::renderLine($message);

            self::renderLine('Restoring database');
            exec(self::getPhpBin() . " composer.phar restore --force --auto 2>/dev/null");
            self::renderLine('Done!');

            exit(1);
        }
    }

    /**
     * @deprecated use self::postUpdate() instead
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
                // do not replace index.php condition
                if ($f->getFilename() === 'index.php' && file_exists($f->getFilename())) {
                    continue;
                }
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
     * Copy root files
     */
    private static function copyRootFiles(): void
    {
        if (self::isInstalled()) {
            return;
        }

        self::renderLine('Copying system files');
        self::copyDir(self::$rootPath . '/vendor/atrocore/core/copy', self::$rootPath);
    }

    /**
     * Update modules list
     */
    private static function updateModulesList(): void
    {
        if (self::isInstalled() && !self::isChanged()) {
            return;
        }

        self::renderLine('Updating list of used modules');
        file_put_contents('data/modules.json', json_encode(self::getModules()));
    }

    /**
     * Copy modules event class
     */
    private static function copyModulesEvent(): void
    {
        if (self::isInstalled() && !self::isChanged()) {
            return;
        }

        self::renderLine('Copying post-install & post-delete scripts for modules');
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
        if (self::isInstalled() && !self::isChanged()) {
            return;
        }

        self::renderLine('Copying migration scripts');

        // prepare data
        $data = [];

        $data['Atro'] = 'vendor/atrocore/core/app/Atro/Migrations';

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
            $moduleName = $row['extra']['atroId'] ?? $row['extra']['treoId'];

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
                    if (!empty($package['extra']['atroId']) || !empty($package['extra']['treoId'])) {
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
        $file = 'first_update.log';
        if (file_exists($file)) {
            self::renderLine('Uploading demo-data');

            $content = @file_get_contents(trim(file_get_contents($file)));
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
                unlink($file);
            }
        }
    }


    /**
     * Clear cache
     */
    private static function clearCache()
    {
        if (!self::isInstalled()) {
            return;
        }

        self::renderLine('Clearing cache');

        self::removeDir('data/cache');
        self::createDir('data/cache');

        self::$container->get('config')->remove('cacheTimestamp');
        self::$container->get('config')->save();
    }

    /**
     * Update client files
     */
    private static function updateClientFiles(): void
    {
        if (self::isInstalled() && !self::isChanged()) {
            return;
        }

        self::renderLine('Copying frontend files');

        self::removeDir('client');
        self::copyDir(dirname(CORE_PATH) . '/client', 'client');
        foreach (self::$container->get('moduleManager')->getModules() as $module) {
            self::copyDir($module->getClientPath(), 'client');
        }
    }

    /**
     * Create config
     */
    private static function createConfig(): void
    {
        // prepare config path
        $path = self::CONFIG_PATH;

        if (!file_exists($path)) {
            self::renderLine('Creating main config');

            // get default data
            $data = include 'vendor/atrocore/atrocore-legacy/app/Espo/Core/defaults/config.php';

            $data['passwordSalt'] = mb_substr(md5((string)time()), 0, 9);

            $data['cryptKey'] = md5(uniqid());

            // get content
            $content = "<?php\nreturn " . self::$container->get('fileManager')->varExport($data) . ";\n?>";

            // create config
            file_put_contents($path, $content);
        }
    }

    /**
     * Init events
     */
    private static function initEvents(): void
    {
        if (!self::isInstalled()) {
            return;
        }

        if (!self::isChanged()) {
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
                self::renderLine('Calling post-install script for ' . $row['id']);
                self::$container->get('moduleManager')->getModuleInstallDeleteObject($row['id'])->afterInstall();
            }
        }

        // call afterDelete event
        if (!empty($composerDiff['delete'])) {
            // run
            foreach ($composerDiff['delete'] as $row) {
                self::renderLine('Calling post-delete script for ' . $row['id']);
                self::$container->get('moduleManager')->getModuleInstallDeleteObject($row['id'])->afterDelete();
            }
        }
    }

    /**
     * Run migrations
     */
    private static function runMigrations(): void
    {
        if (!self::isInstalled()) {
            return;
        }

        $data = self::getComposerDiff()['update'];
        if (empty($data)) {
            return;
        }

        $modulesToMigrate = [];
        foreach (self::getModules() as $id) {
            if (isset($data[$id])) {
                $modulesToMigrate[] = $id;
            }
        }
        if (isset($data['Atro'])) {
            array_unshift($modulesToMigrate, 'Atro');
        }

        /** @var \Atro\Core\Migration\Migration $migrationManager */
        $migrationManager = self::$container->get('migration');

        $res = [];
        foreach ($modulesToMigrate as $k => $id) {
            $moduleNumber = $k + 1;
            foreach ($migrationManager->getMigrationsToExecute($id, self::prepareVersion($data[$id]['from']), self::prepareVersion($data[$id]['to'])) as $k1 => $row) {
                $migrationDate = $row['migration']->getMigrationDateTime();
                if ($migrationDate === null) {
                    $sortOrder = (float)"$moduleNumber.$k1";
                } else {
                    $sortOrder = $migrationDate->getTimestamp();
                }
                $res[] = array_merge(['sortOrder' => $sortOrder], $row);
            }
        }

        usort($res, function ($a, $b) {
            if ($a['sortOrder'] == $b['sortOrder']) {
                return 0;
            }
            return ($a['sortOrder'] < $b['sortOrder']) ? -1 : 1;
        });

        foreach ($res as $row) {
            self::renderLine("Run migration {$row['moduleId']} {$row['version']}");
            $row['migration']->{$row['method']}();
        }
    }

    private static function regenerateMeasures()
    {
        if (!self::isInstalled()) {
            return;
        }

        if (!self::isChanged()) {
            return;
        }

        self::renderLine('Regenerating measures');
        exec(self::getPhpBin() . " index.php regenerate measures >/dev/null");
    }

    private static function regenerateLists()
    {
        if (!self::isInstalled()) {
            return;
        }

        if (!self::isChanged()) {
            return;
        }

        self::renderLine('Regenerating lists');
        exec(self::getPhpBin() . " index.php regenerate lists >/dev/null");
    }

    private static function regenerateUiHandlers()
    {
        if (!self::isInstalled()) {
            return;
        }

        if (!self::isChanged()) {
            return;
        }

        self::renderLine('Regenerating UI handlers');
        exec(self::getPhpBin() . " index.php regenerate ui handlers >/dev/null");
    }

    private static function refreshTranslations()
    {
        if (!self::isInstalled()) {
            return;
        }

        if (!self::isChanged()) {
            return;
        }

        self::renderLine('Refreshing translations');
        exec(self::getPhpBin() . " index.php refresh translations >/dev/null");
    }

    /**
     * Send Notification Admin Users when updated composer
     */
    private static function sendNotification(): void
    {
        if (!self::isInstalled()) {
            return;
        }

        if (!self::isChanged()) {
            return;
        }

        self::renderLine('Sending notification(s) to admin users');

        try {
            /** @var EntityManager $em */
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
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Sending notification(s) to admin users: ' . $e->getMessage());
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
        /** @var Language $language */
        $language = self::$container->get('language');

        if ($module['id'] !== 'Atro') {
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
            $moduleId = $package['extra']['atroId'] ?? $package['extra']['treoId'];
            if ($moduleId === 'Treo') {
                $moduleId = 'Atro';
            }
            if (!isset($newData[$package['name']])) {
                $result['delete'][$moduleId] = [
                    'id'      => $moduleId,
                    'package' => $package,
                    'from'    => null,
                    'to'      => null
                ];
            } elseif ($package['version'] != $newData[$package['name']]['version']) {
                $moduleId = $newData[$package['name']]['extra']['atroId'] ?? $newData[$package['name']]['extra']['treoId'];
                if ($moduleId === 'Treo') {
                    $moduleId = 'Atro';
                }
                $result['update'][$moduleId] = [
                    'id'      => $moduleId,
                    'package' => $newData[$package['name']],
                    'from'    => $package['version'],
                    'to'      => $newData[$package['name']]['version']
                ];
            }
        }
        foreach ($newData as $package) {
            if (!isset($oldData[$package['name']])) {
                $moduleId = $package['extra']['atroId'] ?? $package['extra']['treoId'];
                $result['install'][$moduleId] = [
                    'id'      => $moduleId,
                    'package' => $package,
                    'from'    => null,
                    'to'      => null
                ];
            }
        }

        return $result;
    }

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

        $checkUpdatesLog = 'data/check-updates.log';
        if (file_exists($checkUpdatesLog)) {
            unlink($checkUpdatesLog);
        }

        $publicDataFile = 'data/publicData.json';
        if (file_exists($publicDataFile)) {
            $publicData = json_decode(file_get_contents($publicDataFile), true);
        }
        if (empty($publicData) || !is_array($publicData)) {
            $publicData = [];
        }
        $publicData['isNeedToUpdate'] = false;
        file_put_contents($publicDataFile, json_encode($publicData));

        try {
            /** @var EntityManager $em */
            $em = self::$container->get('entityManager');
            foreach ($em->getRepository('Storage')->find() as $storage) {
                self::$container->get($storage->get('type') . 'Storage')->deleteCache($storage);
            }
            self::$container->get('fileManager')->removeAllInDir(self::PDF_IMAGE_DIR);
        } catch (\Throwable $e) {
        }

        try {
            MassDownload::clearCache();
        } catch (\Throwable $e) {
        }

        try {
            $list = \Atro\Core\ModuleManager\Manager::getList();
            foreach ($list as $module) {
                $className = "\\$module\\Module";
                try {
                    $className::afterUpdate();
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        self::renderLine('Done!');
        exit(0);
    }

    private static function isChanged(): bool
    {
        $composerDiff = self::getComposerDiff();

        return !empty($composerDiff['install']) || !empty($composerDiff['update']) || !empty($composerDiff['delete']);
    }

    private static function getPhpBin(): string
    {
        if (self::$container->get('config')->get('phpBinPath')) {
            return self::$container->get('config')->get('phpBinPath');
        }

        if (isset($_SERVER['PHP_PATH']) && !empty($_SERVER['PHP_PATH'])) {
            return $_SERVER['PHP_PATH'];
        }

        if (!empty($_SERVER['_'])) {
            return $_SERVER['_'];
        }

        return defined("PHP_BINDIR") ? PHP_BINDIR . DIRECTORY_SEPARATOR . 'php' : 'php';
    }

    private static function isInstalled(): bool
    {
        if (!file_exists(self::CONFIG_PATH)) {
            return false;
        }

        return self::$container->get('config')->get('isInstalled', false);
    }
}
