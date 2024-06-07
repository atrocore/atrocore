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

namespace Atro\Services;

use Atro\Core\Templates\Services\HasContainer;
use Atro\Core\Exceptions;
use Atro\Core\Exceptions\Error;
use Espo\Core\Utils\Json;
use Slim\Http\Request;
use Atro\Core\Application;
use Atro\Core\ModuleManager\Manager as ModuleManager;

class Composer extends HasContainer
{
    const CHECK_UP_FILE = 'data/composer-check-up.log';

    /**
     * @var string
     */
    public static $composer = 'composer.json';

    /**
     * @var string
     */
    public static $composerLock = 'composer.lock';

    /**
     * @var string
     */
    public static $stableComposer = 'data/stable-composer.json';

    private array $packages = [];

    /**
     * Get composer.json
     *
     * @return array
     */
    public static function getComposerJson(): array
    {
        return Json::decode(file_get_contents(self::$composer), true);
    }

    public static function getSettingVersion(array $composerData, string $name): string
    {
        if (isset($composerData['require'][$name])) {
            return ModuleManager::prepareVersion($composerData['require'][$name]);
        }

        return '';
    }

    /**
     * Set composer.json
     *
     * @param array $data
     *
     * @return void
     */
    public static function setComposerJson(array $data): void
    {
        file_put_contents(self::$composer, Json::encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get stable-composer.json
     *
     * @return array
     */
    public static function getStableComposerJson(): array
    {
        if (!file_exists(self::$stableComposer)) {
            return self::getComposerJson();
        }

        return Json::decode(file_get_contents(self::$stableComposer), true);
    }

    /**
     * Get core version
     *
     * @return string
     */
    public static function getCoreVersion(): string
    {
        if (file_exists(self::$composerLock)) {
            $data = json_decode(file_get_contents(self::$composerLock), true);
            if (!empty($data['packages'])) {
                foreach ($data['packages'] as $package) {
                    if ($package['name'] == 'atrocore/core') {
                        return $package['version'];
                    }
                }
            }
        }

        return '-';
    }

    /**
     * @return array
     */
    public function checkUpdate(): array
    {
        /**
         * Is daemon enabled ?
         */
        file_put_contents(self::CHECK_UP_FILE, '1');
        sleep(2);
        if (file_exists(self::CHECK_UP_FILE)) {
            return [
                'status'  => false,
                'message' => $this->translate('daemonDisabled', 'labels', 'Composer')
            ];
        }

        /**
         * Is Queue Manager running ?
         */
        $queueItem = $this
            ->getEntityManager()
            ->getRepository('QueueItem')
            ->select(['id'])
            ->where(['status' => ['Pending', 'Running']])
            ->findOne();
        if (!empty($queueItem)) {
            return [
                'status'  => false,
                'message' => $this->translate('queueManagerRunning', 'labels', 'Composer')
            ];
        }

        return [
            'status'  => true,
            'message' => ''
        ];
    }

    /**
     * Run update
     *
     * @return bool
     */
    public function runUpdate(): bool
    {
        file_put_contents(Application::COMPOSER_LOG_FILE, $this->getUser()->get('id'));

        return true;
    }

    /**
     * Cancel changes
     */
    public function cancelChanges(): void
    {
        if (file_exists(self::$stableComposer)) {
            file_put_contents(self::$composer, file_get_contents(self::$stableComposer));
        }
    }

    /**
     * Update composer
     *
     * @param string $package
     * @param string $version
     *
     * @throws Error
     */
    public function update(string $package, string $version): void
    {
        // get composer.json data
        $data = self::getComposerJson();

        // prepare data
        $data['require'] = array_merge($data['require'], [$package => $version]);

        // set composer.json data
        self::setComposerJson($data);
    }

    /**
     * Delete composer
     *
     * @param string $package
     *
     * @throws Error
     */
    public function delete(string $package): void
    {
        // get composer.json data
        $data = self::getComposerJson();

        if (isset($data['require'][$package])) {
            unset($data['require'][$package]);
        }

        // set composer.json data
        self::setComposerJson($data);
    }

    /**
     * Get composer diff
     *
     * @return array
     */
    public function getComposerDiff(): array
    {
        return $this->compareComposerSchemas();
    }

    /**
     * Get list
     *
     * @return array
     * @throws Exceptions\Error
     */
    public function getList(): array
    {
        $this->loadPackages();

        // prepare composer data
        $composerData = self::getComposerJson();

        // get diff
        $composerDiff = $this->getComposerDiff();

        $list = [
            [
                'id'             => 'TreoCore',
                'name'           => $this->translate('Core'),
                'description'    => $this->translate('Core', 'descriptions'),
                'currentVersion' => self::getCoreVersion(),
                'latestVersion'  => $this->getLatestVersion('Atro'),
                'isSystem'       => true,
                'isComposer'     => true,
                'status'         => $this->getModuleStatus($composerDiff, 'Atro'),
                'settingVersion' => self::getSettingVersion($composerData, 'atrocore/core')
            ]
        ];

        // for installed modules
        foreach ($this->getInstalledModules() as $id => $module) {
            $list[$id] = [
                'id'             => $id,
                'name'           => (empty($module->getName())) ? $id : $module->getName(),
                'description'    => $module->getDescription(),
                'currentVersion' => $module->getVersion(),
                'latestVersion'  => $this->getLatestVersion($id),
                'isSystem'       => $module->isSystem(),
                'isComposer'     => !empty($module->getVersion()),
                'status'         => $this->getModuleStatus($composerDiff, $id),
                'settingVersion' => self::getSettingVersion($composerData, $module->getComposerName()),
                'usage'          => $this->getUsage($id),
                'expirationDate' => $this->getExpirationDate($id),
            ];
        }

        // for not installed modules
        foreach ($composerDiff['install'] as $row) {
            $item = [
                'id'             => $row['id'],
                'name'           => $row['id'],
                'description'    => '',
                'currentVersion' => '',
                'latestVersion'  => '',
                'isSystem'       => false,
                'isComposer'     => true,
                'status'         => 'install',
                'settingVersion' => self::getSettingVersion($composerData, $this->getComposerName($row['id']))
            ];

            // get package
            if (!empty($package = $this->getPackage($row['id']))) {
                $item['name'] = $package->get('name');
                $item['description'] = $package->get('description');
            }

            $list[$row['id']] = $item;
        }

        return [
            'total' => count($list),
            'list'  => array_values($list)
        ];
    }

    public function installModule(string $id, string $version = null): bool
    {
        if (empty($version)) {
            $version = '*';
        }

        $name = $this->getComposerName($id);

        if (!$this->isVersionValid($version)) {
            throw new Exceptions\Error($this->translateError('versionIsInvalid'));
        }

        $this->update($name, $version);

        return true;
    }

    public function updateModule(string $id, string $version): bool
    {
        $name = $this->getComposerName($id);

        if (!$this->isVersionValid($version)) {
            throw new Exceptions\Error($this->translateError('versionIsInvalid'));
        }

        $this->update($name, $version);

        return true;
    }

    public function deleteModule(string $id): bool
    {
        $name = $this->getComposerName($id);

        if ($name !== 'atrocore/core' && !empty($this->getInstalledModule($id))) {
            $this->delete($name);
        }

        return true;
    }

    public function cancel(string $id): bool
    {
        $name = $this->getComposerName($id);

        $composerData = self::getComposerJson();

        if (!empty($value = self::getStableComposerJson()['require'][$name])) {
            $composerData['require'][$name] = $value;
        } elseif (isset($composerData['require'][$name])) {
            unset($composerData['require'][$name]);
        }

        self::setComposerJson($composerData);

        return true;
    }

    protected function getComposerName(string $id): string
    {
        if ($id === 'TreoCore') {
            return 'atrocore/core';
        }

        $package = $this->getPackage($id);
        if (empty($package)) {
            return $id;
        }

        return $package->get('packageId');
    }

    public function getLogs(Request $request): array
    {
        /** @var \Espo\Repositories\Note $repo */
        $repo = $this->getEntityManager()->getRepository('Note');

        $result = [
            'list'  => [],
            'total' => $repo->where(['parentType' => 'ModuleManager'])->count()
        ];

        if ($result['total'] > 0) {
            $result['list'] = $repo
                ->where(['parentType' => 'ModuleManager'])
                ->order('number', 'DESC')
                ->limit((int)$request->get('offset'), (int)$request->get('maxSize'))
                ->find()
                ->toArray();
        }

        return $result;
    }

    public function loadPackages(): void
    {
        $this->packages = $this->getContainer()->get('serviceFactory')->create('TreoStore')->getRemotePackages();
    }

    public function getLatestVersion(string $moduleId): string
    {
        foreach ($this->packages as $row) {
            if ($row['treoId'] === $moduleId && !empty($row['versions'])) {
                $latest = array_shift($row['versions']);
                return $latest['version'];
            }
        }

        return '';
    }

    public function getUsage(string $moduleId): ?string
    {
        foreach ($this->packages as $row) {
            if ($row['treoId'] === $moduleId && !empty($row['usage'])) {
                return $row['usage'];
            }
        }

        return null;
    }

    public function getExpirationDate(string $moduleId): ?string
    {
        foreach ($this->packages as $row) {
            if ($row['treoId'] === $moduleId && !empty($row['expirationDate'])) {
                return $row['expirationDate'];
            }
        }

        return null;
    }

    /**
     * @return array
     */
    protected function compareComposerSchemas(): array
    {
        // prepare result
        $result = [
            'install' => [],
            'update'  => [],
            'delete'  => [],
        ];

        if (!file_exists(self::$stableComposer)) {
            // prepare data
            $data = Json::encode(['require' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents(self::$stableComposer, $data);
        }

        // prepare data
        $composerData = self::getComposerJson();
        $composerStableData = Json::decode(file_get_contents(self::$stableComposer), true);
        foreach ($composerData['require'] as $package => $version) {
            if (!isset($composerStableData['require'][$package])) {
                $result['install'][] = [
                    'id'      => $this->getModuleId($package),
                    'package' => $package
                ];
            } elseif ($version != $composerStableData['require'][$package]) {
                // prepare id
                $id = $this->getStoredModuleId($package);

                if ($id === 'Treo') {
                    $result['update'][] = [
                        'id'      => $id,
                        'package' => $package,
                        'from'    => self::getCoreVersion()
                    ];
                } else {
                    if (!empty($module = $this->getModule($id))) {
                        $result['update'][] = [
                            'id'      => $id,
                            'package' => $package,
                            'from'    => $module->getVersion()
                        ];
                    }
                }
            }
        }
        foreach ($composerStableData['require'] as $package => $version) {
            if (!isset($composerData['require'][$package])) {
                $result['delete'][] = [
                    'id'      => $this->getStoredModuleId($package),
                    'package' => $package
                ];
            }
        }

        return $result;
    }

    /**
     * Get module ID
     *
     * @param string $packageId
     *
     * @return string
     */
    protected function getModuleId(string $packageId): string
    {
        // prepare result
        $result = $packageId;

        foreach ($this->getPackages() as $package) {
            if ($package['packageId'] == $packageId) {
                $result = $package['id'];
            }
        }

        return $result;
    }

    /**
     * Get module ID (by composer.lock)
     *
     * @param string $packageId
     *
     * @return string
     */
    protected function getStoredModuleId(string $packageId): string
    {
        // parse composer.lock
        if (file_exists('composer.lock')) {
            $composer = json_decode(file_get_contents('composer.lock'), true);
            if (!empty($composer['packages'])) {
                foreach ($composer['packages'] as $v) {
                    if ($v['name'] == $packageId && !empty($v['extra']['treoId'])) {
                        return $v['extra']['treoId'];
                    }
                }
            }
        }

        return $packageId;
    }

    /**
     * Get module data
     *
     * @param string $id
     *
     * @return object
     */
    protected function getModule(string $id)
    {
        return $this->getContainer()->get('moduleManager')->getModule($id);
    }

    /**
     * @return array
     */
    protected function getPackages(): array
    {
        // prepare result
        $result = [];

        // find
        $data = $this
            ->getEntityManager()
            ->getRepository('TreoStore')
            ->order('id', true)
            ->find();

        if (count($data) > 0) {
            foreach ($data as $row) {
                $result[$row->get('id')] = $row->toArray();
                $result[$row->get('id')]['versions'] = json_decode(json_encode($row->get('versions')), true);
            }
        }

        return $result;
    }

    /**
     * Get module status
     *
     * @param array  $diff
     * @param string $id
     *
     * @return mixed
     */
    protected function getModuleStatus(array $diff, string $id)
    {
        foreach ($diff as $status => $row) {
            foreach ($row as $item) {
                if ($item['id'] == $id) {
                    return $status;
                }
            }
        }

        return null;
    }

    /**
     * Translate error
     *
     * @param string $key
     *
     * @return string
     */
    protected function translateError(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Composer');
    }

    /**
     * Is version valid?
     *
     * @param string $version
     *
     * @return bool
     */
    protected function isVersionValid(string $version): bool
    {
        // prepare result
        $result = true;

        // create version parser
        $versionParser = new \Composer\Semver\VersionParser();

        try {
            $versionParser->parseConstraints($version)->getPrettyString();
            if (preg_match("/^(.*)\-$/", $version)) {
                $result = false;
            }
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * @return ModuleManager
     */
    private function getModuleManager(): ModuleManager
    {
        return $this->getContainer()->get('moduleManager');
    }

    /**
     * @return array
     */
    private function getInstalledModules(): array
    {
        return $this->getModuleManager()->getModules();
    }

    /**
     * @return mixed
     */
    private function getInstalledModule(string $id)
    {
        return $this->getModuleManager()->getModule($id);
    }

    /**
     * @param string $id
     *
     * @return mixed
     * @throws Exceptions\Error
     */
    private function getPackage(string $id)
    {
        return $this->getEntityManager()->getEntity('TreoStore', $id);
    }
}
