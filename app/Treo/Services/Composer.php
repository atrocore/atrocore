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

namespace Treo\Services;

use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Json;
use Espo\Core\Exceptions;
use Slim\Http\Request;
use Treo\Composer\PostUpdate;
use Treo\Core\ModuleManager\Manager as ModuleManager;

/**
 * Composer service
 */
class Composer extends AbstractService
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
    public static $stableComposer = PostUpdate::STABLE_COMPOSER_JSON;

    /**
     * Get composer.json
     *
     * @return array
     */
    public static function getComposerJson(): array
    {
        return Json::decode(file_get_contents(self::$composer), true);
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
        file_put_contents(COMPOSER_LOG, $this->getUser()->get('id'));

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
        if (!empty($this->getConfig()->get('isUpdating'))) {
            throw new Error('System is updating now');
        }

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
        if (!empty($this->getConfig()->get('isUpdating'))) {
            throw new Error('System is updating now');
        }

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
        // prepare result
        $result = [
            'total' => 1,
            'list'  => [
                [
                    'id'             => 'TreoCore',
                    'name'           => $this->translate('Core'),
                    'description'    => $this->translate('Core', 'descriptions'),
                    'settingVersion' => self::getComposerJson()['require']['atrocore/core'],
                    'currentVersion' => self::getCoreVersion(),
                    'versions'       => [],
                    'isSystem'       => true,
                    'isComposer'     => true,
                    'status'         => '',
                ]
            ]
        ];

        // prepare composer data
        $composerData = self::getComposerJson();

        // get diff
        $composerDiff = $this->getComposerDiff();

        // for installed modules
        foreach ($this->getInstalledModules() as $id => $module) {
            $result['list'][$id] = [
                'id'             => $id,
                'name'           => (empty($module->getName())) ? $id : $module->getName(),
                'description'    => $module->getDescription(),
                'settingVersion' => '*',
                'currentVersion' => $module->getVersion(),
                'versions'       => [],
                'isSystem'       => $module->isSystem(),
                'isComposer'     => !empty($module->getVersion()),
                'status'         => $this->getModuleStatus($composerDiff, $id),
            ];

            // set available versions
            if (!empty($package = $this->getPackage($id))) {
                $result['list'][$id]['versions'] = json_decode(json_encode($package->get('versions')), true);
            }

            // set settingVersion
            if (isset($composerData['require'][$module->getComposerName()])) {
                $settingVersion = $composerData['require'][$module->getComposerName()];
                $result['list'][$id]['settingVersion'] = ModuleManager::prepareVersion($settingVersion);
            }
        }

        // for not installed modules
        foreach ($composerDiff['install'] as $row) {
            $item = [
                'id'             => $row['id'],
                'name'           => $row['id'],
                'description'    => '',
                'settingVersion' => '*',
                'currentVersion' => '',
                'isSystem'       => false,
                'isComposer'     => true,
                'status'         => 'install'
            ];

            // get package
            if (!empty($package = $this->getPackage($row['id']))) {
                // set name
                $item['name'] = $package->get('name');

                // set description
                $item['description'] = $package->get('description');

                // set settingVersion
                if (!empty($composerData['require'][$package->get('packageId')])) {
                    $settingVersion = $composerData['require'][$package->get('packageId')];
                    $item['settingVersion'] = ModuleManager::prepareVersion($settingVersion);
                }
            }
            // push
            $result['list'][$row['id']] = $item;
        }

        // prepare result
        $result['list'] = array_values($result['list']);
        $result['total'] = count($result['list']);

        return $result;
    }

    /**
     * Install module
     *
     * @param string $id
     * @param string $version
     *
     * @return bool
     * @throws Exceptions\Error
     */
    public function installModule(string $id, string $version = null): bool
    {
        // prepare version
        if (empty($version)) {
            $version = '*';
        }

        // validation
        if (empty($package = $this->getPackage($id))) {
            throw new Exceptions\Error($this->translateError('noSuchModule'));
        }
        if (!empty($this->getInstalledModule($id))) {
            throw new Exceptions\Error($this->translateError('suchModuleIsAlreadyInstalled'));
        }
        if (!$this->isVersionValid($version)) {
            throw new Exceptions\Error($this->translateError('versionIsInvalid'));
        }

        // update composer.json
        $this->update($package->get('packageId'), $version);

        return true;
    }

    /**
     * Update module
     *
     * @param string $id
     * @param string $version
     *
     * @return bool
     * @throws Exceptions\Error
     */
    public function updateModule(string $id, string $version): bool
    {
        // prepare params
        $package = $this->getInstalledModule($id);

        // validation
        if (empty($this->getPackage($id))) {
            throw new Exceptions\Error($this->translateError('noSuchModule'));
        }
        if (empty($package)) {
            throw new Exceptions\Error($this->translateError('moduleWasNotInstalled'));
        }
        if (!$this->isVersionValid($version)) {
            throw new Exceptions\Error($this->translateError('versionIsInvalid'));
        }

        // update composer.json
        $this->update($package->getComposerName(), $version);

        return true;
    }

    /**
     * Delete module
     *
     * @param string $id
     *
     * @return bool
     * @throws Exceptions\Error
     */
    public function deleteModule(string $id): bool
    {
        // get module
        $package = $this->getInstalledModule($id);

        // prepare modules
        if ($package->isSystem($id)) {
            throw new Exceptions\Error($this->translateError('isSystem'));
        }

        // validation
        if (empty($package)) {
            throw new Exceptions\Error($this->translateError('noSuchModule'));
        }

        // update composer.json
        $this->delete($package->getComposerName());

        return true;
    }

    /**
     * Cancel module changes
     *
     * @param string $id
     *
     * @return bool
     * @throws Exceptions\Error
     */
    public function cancel(string $id): bool
    {
        // prepare result
        $result = false;

        // get package
        if (!empty($package = $this->getPackage($id))) {
            // get name
            $name = $package->get('packageId');

            // get composer data
            $composerData = self::getComposerJson();

            if (!empty($value = self::getStableComposerJson()['require'][$name])) {
                $composerData['require'][$name] = $value;
            } elseif (isset($composerData['require'][$name])) {
                unset($composerData['require'][$name]);
            }

            // save
            self::setComposerJson($composerData);

            // prepare result
            $result = true;
        }

        return $result;
    }

    /**
     * Get logs
     *
     * @param Request $request
     *
     * @return array
     */
    public function getLogs(Request $request): array
    {
        // prepare result
        $result = [
            'total' => 0,
            'list'  => []
        ];

        // prepare where
        $where = [
            'whereClause' => [
                'parentType' => 'ModuleManager'
            ],
            'offset'      => (int)$request->get('offset'),
            'limit'       => (int)$request->get('maxSize'),
            'orderBy'     => 'number',
            'order'       => 'DESC'
        ];

        $result['total'] = $this->getNoteCount($where);

        if ($result['total'] > 0) {
            if (!empty($request->get('after'))) {
                $where['whereClause']['createdAt>'] = $request->get('after');
            }

            // get collection
            $result['list'] = $this->getNoteData($where);
        }

        return $result;
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

                $result['update'][] = [
                    'id'      => $id,
                    'package' => $package,
                    'from'    => $this->getModule($id)->getVersion()
                ];
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
     * Get note count
     *
     * @param array $where
     *
     * @return int
     */
    protected function getNoteCount(array $where): int
    {
        return $this
            ->getEntityManager()
            ->getRepository('Note')
            ->count(['whereClause' => $where['whereClause']]);
    }

    /**
     * Get note data
     *
     * @param array $where
     *
     * @return array
     */
    protected function getNoteData(array $where): array
    {
        $entities = $this
            ->getEntityManager()
            ->getRepository('Note')
            ->find($where);

        return !empty($entities) ? $entities->toArray() : [];
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
