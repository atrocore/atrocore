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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

namespace Espo\Core;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Treo\Core\ModuleManager\Manager as ModuleManager;

/**
 * Class DataManager
 */
class DataManager
{
    public const CACHE_DIR_PATH = 'data/cache';

    public const PUBLIC_DATA_FILE_PATH = 'data/publicData.json';

    /**
     * @var Container
     */
    private $container;

    /**
     * DataManager constructor.
     *
     * @param Container $container
     */
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
        if (!$this->getConfig()->get('useCache', false) || substr(php_sapi_name(), 0, 3) == 'cli') {
            return false;
        }

        if (!$this->getModuleManager()->isLoaded()) {
            return false;
        }

        $cacheTimestamp = $this->getConfig()->get('cacheTimestamp');
        if (empty($cacheTimestamp)) {
            $this->getConfig()->set('cacheTimestamp', time());
            $this->getConfig()->save();
        }

        $timeDiff = time() - $cacheTimestamp;
        if ($timeDiff < 30) {
            return false;
        }

        self::createCacheDir();
        file_put_contents(self::CACHE_DIR_PATH . "/{$name}.json", Json::encode($data));

        return true;
    }

    /**
     * @param string $name
     * @param bool   $isArray
     *
     * @return mixed
     */
    public function getCacheData(string $name, $isArray = true)
    {
        if (!$this->getConfig()->get('useCache', false) || !$this->isCacheExist($name)) {
            return null;
        }

        return Json::decode(file_get_contents(self::CACHE_DIR_PATH . "/{$name}.json"), $isArray);
    }

    /**
     * Rebuild the system with metadata, database and cache clearing
     *
     * @return bool
     */
    public function rebuild($entityList = null)
    {
        $this->populateConfigParameters();

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
        $metadata = $this->container->get('metadata');

        $metadata->init(true);

        $ormData = $this->container->get('ormMetadata')->getData(true);

        $this->clearCache();

        return empty($ormData) ? false : true;
    }

    /**
     * Rebuild scheduledJobs
     */
    public function rebuildScheduledJobs()
    {
        $metadata = $this->container->get('metadata');
        $entityManager = $this->container->get('entityManager');

        $jobs = $metadata->get(['entityDefs', 'ScheduledJob', 'jobs'], array());

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
     * Populate config parameters
     */
    protected function populateConfigParameters()
    {
        $pdo = $this->container->get('pdo');
        $query = "SHOW VARIABLES LIKE 'ft_min_word_len'";
        $sth = $pdo->prepare($query);
        $sth->execute();

        $fullTextSearchMinLength = null;
        if ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            if (isset($row['Value'])) {
                $fullTextSearchMinLength = intval($row['Value']);
            }
        }

        $this->getConfig()->set('fullTextSearchMinLength', $fullTextSearchMinLength);
        $this->getConfig()->save();
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
}
