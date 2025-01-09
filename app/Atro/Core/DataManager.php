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

namespace Atro\Core;

use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Util;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Core\Utils\Database\Schema\Schema;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;

class DataManager
{
    public const CACHE_DIR_PATH = 'data/cache';

    public const PUBLIC_DATA_FILE_PATH = 'data/publicData.json';

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public static function pushPublicData(string $key, $value): void
    {
        if (file_exists(self::PUBLIC_DATA_FILE_PATH)) {
            $data = file_get_contents(self::PUBLIC_DATA_FILE_PATH);

            if (JSON::isJSON($data)) {
                $result = JSON::decode($data, true);
            }
        }

        if (empty($result) || !is_array($result)) {
            $result = [];
        }

        file_put_contents(self::PUBLIC_DATA_FILE_PATH, JSON::encode(array_merge($result, [$key => $value])));
    }

    public static function getPublicData(string $key)
    {
        if (file_exists(self::PUBLIC_DATA_FILE_PATH)) {
            $data = file_get_contents(self::PUBLIC_DATA_FILE_PATH);

            if (JSON::isJSON($data)) {
                $result = JSON::decode($data, true);
            }
        }

        if (empty($result) || !is_array($result) || empty($result[$key])) {
            return null;
        }

        return $result[$key];
    }

    public static function createCacheDir(): void
    {
        if (!file_exists(self::CACHE_DIR_PATH)) {
            @mkdir(self::CACHE_DIR_PATH, 0777, true);
        }
    }

    public function isCacheExist(string $name): bool
    {
        return file_exists(self::CACHE_DIR_PATH . "/{$name}.json");
    }

    public function setCacheData(string $name, $data): bool
    {
        if (!$this->isUseCache($name)) {
            return false;
        }

        if (!$this->getModuleManager()->isLoaded()) {
            return false;
        }

        $cacheTimestamp = $this->getConfig()->get('cacheTimestamp');
        if (empty($cacheTimestamp)) {
            $cacheTimestamp = time();
            $this->getConfig()->set('cacheTimestamp', $cacheTimestamp);
            $this->getConfig()->save();
        }

        $timeDiff = time() - $cacheTimestamp;
        if ($timeDiff < 10) {
            return false;
        }

        self::createCacheDir();
        file_put_contents(self::CACHE_DIR_PATH . "/{$name}.json", Json::encode($data));

        return true;
    }

    public function removeCacheData(string $name): void
    {
        unlink(self::CACHE_DIR_PATH . "/{$name}.json");
    }

    public function isUseCache(string $name): bool
    {
        // for iso codes like es_CL or syr_SY
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $name) || preg_match('/^[a-z]{3}_[A-Z]{2}$/', $name)) {
            return true;
        }

        if (str_starts_with($name, 'cron_')) {
            return true;
        }

        if (substr(php_sapi_name(), 0, 3) == 'cli') {
            return false;
        }

        return $this->getConfig()->get('useCache', false);
    }

    public function getCacheData(string $name, $isArray = true)
    {
        if (!$this->isUseCache($name) || !$this->isCacheExist($name)) {
            return null;
        }

        if (!$this->getMemoryStorage()->has($name)) {
            $this->getMemoryStorage()->set($name,
                @json_decode(file_get_contents(self::CACHE_DIR_PATH . "/{$name}.json"), $isArray));
        }

        return $this->getMemoryStorage()->get($name);
    }

    public function rebuild(): bool
    {
        $result = $this->clearCache();

        $result &= $this->rebuildMetadata();

        $result &= $this->rebuildDatabase();

        return $result;
    }

    public function clearCache(): bool
    {
        try {
            Util::removeDir(self::CACHE_DIR_PATH);
            self::createCacheDir();

            $this->getConfig()->remove('cacheTimestamp');
            $this->getConfig()->save();

            self::pushPublicData('dataTimestamp', (new \DateTime())->getTimestamp());
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Cache clearing failed: ' . $e->getMessage());
        }

        return true;
    }

    public function rebuildDatabase(): bool
    {
        try {
            $result = $this->getSchema()->rebuild();
        } catch (\Exception $e) {
            $result = false;
            $GLOBALS['log']->error('Fault to rebuild database schema' . '. Details: ' . $e->getMessage());
        }

        if ($result != true) {
            throw new Exceptions\Error("Error while rebuilding database. See log file for details.");
        }

        self::pushPublicData('isNeedToRebuildDatabase', false);

        return $result;
    }

    public function rebuildMetadata(): bool
    {
        /** @var Metadata $metadata */
        $metadata = $this->container->get('metadata');
        $metadata->init(true);

        $ormData = $this->container->get('ormMetadata')->getData(true);

        return !empty($ormData);
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    private function getModuleManager(): ModuleManager
    {
        return $this->container->get('moduleManager');
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->container->get('memoryStorage');
    }

    public function getSchema(): Schema
    {
        return $this->container->get('schema');
    }
}
