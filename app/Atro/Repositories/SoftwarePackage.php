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

use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class SoftwarePackage extends ReferenceData
{
    private static ?array $composerData = null;

    private ?array $remoteItems = null;

    public static function getComposerData(): array
    {
        if (self::$composerData === null) {
            self::$composerData = json_decode(file_get_contents('composer.json'), true);
        }

        return self::$composerData;
    }

    public function getRemotePackages(): array
    {
        if ($this->remoteItems === null) {
            $cacheFile = 'data/store-cache.json';

            $contents = @file_get_contents('https://packagist.atrocore.com/store.json?id=' . $this->getConfig()->get('appId'));
            if (empty($contents)) {
                if (!file_exists($cacheFile)) {
                    throw new \Error('Failed to retrieve data from the repository.');
                } else {
                    $contents = file_get_contents($cacheFile);
                }
            }

            file_put_contents($cacheFile, $contents);

            $remoteItems = @json_decode($contents, true);
            if (empty($remoteItems)) {
                throw new \Error('Failed to retrieve data from the repository.');
            }

            $this->remoteItems = $remoteItems;
        }

        return $this->remoteItems;
    }

    public function insertEntity(Entity $entity): bool
    {
        return false;
    }

    public function updateEntity(Entity $entity): bool
    {
        return false;
    }

    public function deleteEntity(Entity $entity): bool
    {
        return false;
    }

    protected function getAllItems(array $params = []): array
    {
        $remotePackages = $this->getRemotePackages();

        $composerData = self::getComposerData();

        $items = [];

        $loadOrder = 1;
        foreach (array_merge(['Atro'], ModuleManager::getList()) as $moduleId) {
            $package = $this->getPackage($moduleId);
            if (empty($package['name'])) {
                continue;
            }

            $code = $package['name'];

            $items[] = [
                'id'             => $moduleId,
                'code'           => $code,
                'name'           => $package['extra']['name']['default'] ?? $package['extra']['name'] ?? $moduleId,
                'description'    => $package['extra']['description']['default'] ?? $package['extra']['description'] ?? $moduleId,
                'loadOrder'      => $loadOrder,
                'targetVersion'  => $composerData['require'][$code] ?? null,
                'targetVersions' => !empty($package['version']) ? $this->prepareTargetVersions($code, $package) : [],
                'currentVersion' => $package['version'] ?? null,
                'latestVersion'  => $remotePackages[$code]['versions'][0]['version'] ?? null,
                'expirationDate' => $remotePackages[$code]['expirationDate'] ?? null,
                'usage'          => $remotePackages[$code]['usage'] ?? null,
                'hasDocs'        => true
            ];
            $loadOrder += 1;
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
}
