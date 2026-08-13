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

namespace Atro\Repositories;

use Atro\Core\DataManager;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class SoftwarePackage extends ReferenceData
{
    private const string STORE_URL = 'https://packagist.atrocore.com/store.json';

    // the file name is bound to a time interval, so the existence of the file means the cache is still actual
    private const string CACHE_FILE_MASK = 'data/cache/store-cache-%s.json';
    private const string FAILURE_FILE_MASK = 'data/cache/store-failure-%s.json';

    private const int CACHE_TTL = 3600;           // how long a cache file is considered actual
    private const int FAILURE_RETRY_DELAY = 300;  // do not hammer the unreachable remote on every request
    private const int REQUEST_TIMEOUT = 5;        // instead of the default_socket_timeout of 60 seconds

    private static ?array $composerData = null;

    private ?array $remoteItems = null;

    public static function getComposerData(): array
    {
        if (self::$composerData === null) {
            self::$composerData = json_decode(file_get_contents('composer.json'), true);
        }

        return self::$composerData;
    }

    public static function setComposerData(string $key, $value): void
    {
        self::getComposerData();
        self::$composerData[$key] = $value;

        file_put_contents('composer.json', json_encode(self::$composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function getRemotePackages(): array
    {
        if ($this->remoteItems === null) {
            $cacheFile = sprintf(self::CACHE_FILE_MASK, $this->getTimeInterval(self::CACHE_TTL));

            if (!file_exists($cacheFile) && $this->isRemoteRequestAllowed()) {
                $this->loadRemotePackages($cacheFile);
            }

            if (!file_exists($cacheFile)) {
                // the remote is not reachable, an outdated cache is better than an error
                $cacheFile = $this->getLatestCacheFile();
            }

            $remoteItems = $cacheFile === null ? null : @json_decode(file_get_contents($cacheFile), true);
            if (empty($remoteItems)) {
                throw new \Error('Failed to retrieve data from the repository.');
            }

            $this->remoteItems = $remoteItems;
        }

        return $this->remoteItems;
    }

    private function loadRemotePackages(string $cacheFile): void
    {
        $context = stream_context_create(['http' => ['timeout' => self::REQUEST_TIMEOUT]]);

        $contents = @file_get_contents(self::STORE_URL . '?id=' . $this->getConfig()->get('appId'), false, $context);
        if (empty($contents) || empty(@json_decode($contents, true))) {
            $this->registerFailedRequest();

            return;
        }

        DataManager::createCacheDir();
        file_put_contents($cacheFile, $contents);

        $this->removeOutdatedFiles(self::CACHE_FILE_MASK, $cacheFile);
        $this->removeOutdatedFiles(self::FAILURE_FILE_MASK);
    }

    private function isRemoteRequestAllowed(): bool
    {
        return !file_exists(sprintf(self::FAILURE_FILE_MASK, $this->getTimeInterval(self::FAILURE_RETRY_DELAY)));
    }

    private function registerFailedRequest(): void
    {
        $failureFile = sprintf(self::FAILURE_FILE_MASK, $this->getTimeInterval(self::FAILURE_RETRY_DELAY));

        DataManager::createCacheDir();
        file_put_contents($failureFile, '');

        $this->removeOutdatedFiles(self::FAILURE_FILE_MASK, $failureFile);
    }

    private function getLatestCacheFile(): ?string
    {
        $result = null;
        $latestInterval = null;

        foreach ($this->getFilesByMask(self::CACHE_FILE_MASK) as $file => $interval) {
            if ($latestInterval === null || $interval > $latestInterval) {
                $latestInterval = $interval;
                $result = $file;
            }
        }

        return $result;
    }

    private function removeOutdatedFiles(string $mask, ?string $keep = null): void
    {
        foreach (array_keys($this->getFilesByMask($mask)) as $file) {
            if ($file !== $keep) {
                unlink($file);
            }
        }
    }

    /**
     * @return array file path => time interval it was created for
     */
    private function getFilesByMask(string $mask): array
    {
        [$prefix, $suffix] = explode('%s', $mask);

        $result = [];
        foreach (glob($prefix . '*' . $suffix) ?: [] as $file) {
            $interval = substr($file, strlen($prefix), -strlen($suffix));
            if (is_numeric($interval)) {
                $result[$file] = (int)$interval;
            }
        }

        return $result;
    }

    private function getTimeInterval(int $seconds): int
    {
        return intdiv(time(), $seconds);
    }

    public function insertEntity(Entity $entity): bool
    {
        throw new Error('Not implemented.');
    }

    public function updateEntity(Entity $entity): bool
    {
        if (empty($entity->get('id'))) {
            throw new NotFound();
        }

        if ($entity->isAttributeChanged('targetVersion')) {
            $package = $this->getPackage($entity->get('id'));

            $code = $package['abandoned'] ?? $package['name'] ?? null;

            if (!empty($code)) {
                $composerData = self::getComposerData();
                $composerData['require'][$code] = $entity->get('targetVersion');
                self::setComposerData('require', $composerData['require']);

                return true;
            }
        }

        return false;
    }

    public function deleteEntity(Entity $entity): bool
    {
        throw new Error('Not implemented.');
    }

    protected function getAllItems(array $params = []): array
    {
        $items = [];

        $onlyUninstallable = false;
        foreach ($params['whereClause'] ?? [] as $item) {
            if (!empty($item['onlyUninstallable'])) {
                $onlyUninstallable = true;
            }
        }

        // push the Core
        $this->pushInstalledItem('Atro', $items, $onlyUninstallable);

        // push installed modules
        foreach (ModuleManager::getList() as $moduleId) {
            $this->pushInstalledItem($moduleId, $items, $onlyUninstallable);
        }

        // push available to install modules
        $installed = array_column($items, 'id');
        foreach ($this->getRemotePackages() as $code => $package) {
            if (!in_array($package['id'], $installed) && !empty($package['expirationDate']) && $package['expirationDate'] >= date('Y-m-d')) {
                $this->pushNotInstalledItem($package, $items);
            }
        }

        return $items;
    }

    private function prepareTargetVersions(string $code, array $package): array
    {
        $remotePackages = $this->getRemotePackages();

        // current version can contain a pre-release suffix (2.2.23-RC1, 1.5.0-beta2), so only its numeric part matters
        $currentVersion = $this->prepareVersionForComparison($package['version'] ?? null);

        $result = [];
        foreach ($remotePackages[$code]['versions'] ?? [] as $version) {
            if (empty($version['version'])) {
                continue;
            }

            if ($currentVersion !== null) {
                $targetVersion = $this->prepareVersionForComparison($version['version']);
                // versions older than the installed one cannot be a target
                if ($targetVersion !== null && version_compare($targetVersion, $currentVersion, '<')) {
                    continue;
                }
            }

            $result[] = $version['version'];
        }

        return $result;
    }

    private function pushInstalledItem(string $moduleId, array &$items, bool $onlyUninstallable): void
    {
        $package = $this->getPackage($moduleId);
        if (empty($package['name'])) {
            return;
        }

        $composerData = self::getComposerData();
        $remotePackages = $this->getRemotePackages();

        $code = $package['abandoned'] ?? $package['name'];

        if ($onlyUninstallable) {
            if ($moduleId === 'Atro' || empty($package['version']) || empty($composerData['require'][$code])) {
                return;
            }
        }

        $targetVersion = $composerData['require'][$code] ?? null;
        foreach ($remotePackages[$code]['abandoned'] ?? [] as $abandoned) {
            if (isset($composerData['require'][$abandoned])) {
                $targetVersion = $composerData['require'][$abandoned];
            }
        }

        $items[] = [
            'id'             => $moduleId,
            'code'           => $code,
            'name'           => $package['extra']['name']['default'] ?? $package['extra']['name'] ?? $moduleId,
            'description'    => $package['extra']['description']['default'] ?? $package['extra']['description'] ?? $moduleId,
            'sortOrder'      => count($items) + 1,
            'targetVersion'  => $targetVersion,
            'targetVersions' => !empty($package['version']) ? $this->prepareTargetVersions($code, $package) : [],
            'currentVersion' => $package['version'] ?? null,
            'latestVersion'  => $remotePackages[$code]['versions'][0]['version'] ?? null,
            'expirationDate' => $remotePackages[$code]['expirationDate'] ?? null,
            'usage'          => $remotePackages[$code]['usage'] ?? null,
            'installed'      => true
        ];
    }

    private function pushNotInstalledItem(array $package, array &$items): void
    {
        $items[] = [
            'id'             => $package['id'],
            'code'           => $package['code'],
            'name'           => $package['name'],
            'description'    => $package['description'],
            'sortOrder'      => count($items) + 1,
            'targetVersion'  => null,
            'targetVersions' => $this->prepareTargetVersions($package['code'], $package),
            'currentVersion' => null,
            'latestVersion'  => $package['versions'][0]['version'] ?? null,
            'expirationDate' => $package['expirationDate'],
            'usage'          => $package['usage'] ?? null,
            'installed'      => false
        ];
    }

    private function prepareVersionForComparison(?string $version): ?string
    {
        if (empty($version)) {
            return null;
        }

        $version = ltrim(trim($version), 'vV');

        // cut off any pre-release / build suffix: -RC1, -beta2, -alpha, +build.7 etc.
        if (!preg_match('/^\d+(\.\d+)*/', $version, $matches)) {
            return null;
        }

        return $matches[0];
    }

    private function getPackage(string $id): array
    {
        if (file_exists('composer.lock')) {
            $data = json_decode(file_get_contents('composer.lock'), true);
            if (!empty($data['packages'])) {
                foreach ($data['packages'] as $package) {
                    if (!empty($package['extra']['atroId']) && $package['extra']['atroId'] == $id) {
                        return $package;
                    }
                    // for backward compactibility
                    if (!empty($package['extra']['treoId']) && $package['extra']['treoId'] == $id) {
                        return $package;
                    }
                }
            }
        }

        // for custom loaded modules
        $composerData = self::getComposerData();
        if (!empty($composerData['autoload']['psr-4'][$id . '\\'])) {
            $path = str_replace('/app/', '/', $composerData['autoload']['psr-4'][$id . '\\']);
            if (file_exists($path . 'composer.json')) {
                return json_decode(file_get_contents($path . 'composer.json'), true);
            }
        }

        return [];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('moduleManager');
    }

    protected function getModuleManager(): ModuleManager
    {
        return $this->getInjection('moduleManager');
    }
}
