<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

namespace Espo\Core;

use Atro\Core\KeyValueStorages\StorageInterface;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Espo\ORM\EntityManager;

class DataManager
{
    public const CACHE_DIR_PATH = 'data/cache';

    public const PUBLIC_DATA_FILE_PATH = 'data/publicData.json';

    public const MANDATORY_CACHED = ['translations', 'locales', 'cronLastRunTime'];

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public static function pushPublicData(string $key, $value): void
    {
        if (file_exists(self::PUBLIC_DATA_FILE_PATH)) {
            $result = JSON::decode(file_get_contents(self::PUBLIC_DATA_FILE_PATH), true);
        }

        if (empty($result) || !is_array($result)) {
            $result = [];
        }

        file_put_contents(self::PUBLIC_DATA_FILE_PATH, JSON::encode(array_merge($result, [$key => $value])));
    }

    public static function getPublicData(string $key)
    {
        if (file_exists(self::PUBLIC_DATA_FILE_PATH)) {
            $result = JSON::decode(file_get_contents(self::PUBLIC_DATA_FILE_PATH), true);
        }

        if (empty($result[$key])) {
            return null;
        }

        return $result[$key];
    }

    /**
     * Create cache dir
     */
    public static function createCacheDir(): void
    {
        if (!file_exists(self::CACHE_DIR_PATH)) {
            mkdir(self::CACHE_DIR_PATH, 0777, true);
            sleep(1);
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function isCacheExist(string $name): bool
    {
        return file_exists(self::CACHE_DIR_PATH . "/{$name}.json");
    }

    /**
     * @param string $name
     * @param mixed  $data
     *
     * @return bool
     */
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

    public function isUseCache(string $name): bool
    {
        if (in_array($name, self::MANDATORY_CACHED)) {
            return true;
        }

        if (substr(php_sapi_name(), 0, 3) == 'cli') {
            return false;
        }

        return $this->getConfig()->get('useCache', false);
    }

    /**
     * @param string $name
     * @param bool   $isArray
     *
     * @return mixed
     */
    public function getCacheData(string $name, $isArray = true)
    {
        if (!$this->isUseCache($name) || !$this->isCacheExist($name)) {
            return null;
        }

        if (!$this->getMemoryStorage()->has($name)) {
            $this->getMemoryStorage()->set($name, @json_decode(file_get_contents(self::CACHE_DIR_PATH . "/{$name}.json"), $isArray));
        }

        return $this->getMemoryStorage()->get($name);
    }

    /**
     * Rebuild the system with metadata, database and cache clearing
     *
     * @return bool
     */
    public function rebuild($entityList = null)
    {
        $result = $this->clearCache();

        $result &= $this->rebuildMetadata();

        $result &= $this->rebuildDatabase($entityList);

        $this->rebuildScheduledJobs();

        return $result;
    }

    /**
     * Clear a cache
     *
     * @return bool
     */
    public function clearCache()
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

    /**
     * Rebuild database
     *
     * @return bool
     */
    public function rebuildDatabase($entityList = null)
    {
        try {
            $result = $this->container->get('schema')->rebuild($entityList);
        } catch (\Exception $e) {
            $result = false;
            $GLOBALS['log']->error('Fault to rebuild database schema' . '. Details: ' . $e->getMessage());
        }

        if ($result != true) {
            throw new Exceptions\Error("Error while rebuilding database. See log file for details.");
        }

        self::pushPublicData('isNeedToRebuildDatabase', false);
        $this->clearCache();

        return $result;
    }

    /**
     * Rebuild metadata
     *
     * @return bool
     */
    public function rebuildMetadata()
    {
        /** @var Metadata $metadata */
        $metadata = $this->container->get('metadata');
        $metadata->init(true);

        $ormData = $this->container->get('ormMetadata')->getData(true);

        $this->clearCache();

        return !empty($ormData);
    }

    /**
     * Rebuild scheduledJobs
     */
    public function rebuildScheduledJobs()
    {
        /** @var Metadata $metadata */
        $metadata = $this->container->get('metadata');

        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get('entityManager');

        $jobs = $metadata->get(['entityDefs', 'ScheduledJob', 'jobs'], []);

        foreach ($jobs as $jobName => $defs) {
            if ($jobName && !empty($defs['isSystem']) && !empty($defs['scheduling'])) {
                if (!$entityManager->getRepository('ScheduledJob')->where(
                    array(
                        'job'        => $jobName,
                        'status'     => 'Active',
                        'scheduling' => $defs['scheduling']
                    )
                )->findOne()) {
                    $job = $entityManager->getRepository('ScheduledJob')->where(
                        array(
                            'job' => $jobName
                        )
                    )->findOne();
                    if ($job) {
                        $entityManager->removeEntity($job);
                    }
                    $name = $jobName;
                    if (!empty($defs['name'])) {
                        $name = $defs['name'];
                    }
                    $job = $entityManager->getEntity('ScheduledJob');
                    $job->set(
                        array(
                            'job'        => $jobName,
                            'status'     => 'Active',
                            'scheduling' => $defs['scheduling'],
                            'isInternal' => true,
                            'name'       => $name
                        )
                    );
                    $entityManager->saveEntity($job);
                }
            }
        }
    }

    /**
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    /**
     * @return ModuleManager
     */
    private function getModuleManager(): ModuleManager
    {
        return $this->container->get('moduleManager');
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->container->get('memoryStorage');
    }
}
