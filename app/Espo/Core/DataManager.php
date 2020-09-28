<?php

namespace Espo\Core;

use Treo\Core\Container;

class DataManager
{
    private $container;

    private $cachePath = 'data/cache';


    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
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
        $result = $this->getContainer()->get('fileManager')->removeInDir($this->cachePath);

        if ($result != true) {
            throw new Exceptions\Error("Error while clearing cache");
        }

        $this->updateCacheTimestamp();

        return $result;
    }

    /**
     * Rebuild database
     *
     * @return bool
     */
    public function rebuildDatabase($entityList = null)
    {
        try {
            $result = $this->getContainer()->get('schema')->rebuild($entityList);
        } catch (\Exception $e) {
            $result = false;
            $GLOBALS['log']->error('Fault to rebuild database schema'.'. Details: '.$e->getMessage());
        }

        if ($result != true) {
            throw new Exceptions\Error("Error while rebuilding database. See log file for details.");
        }

        $this->updateCacheTimestamp();

        return $result;
    }

    /**
     * Rebuild metadata
     *
     * @return bool
     */
    public function rebuildMetadata()
    {
        $metadata = $this->getContainer()->get('metadata');

        $metadata->init(true);

        $ormData = $this->getContainer()->get('ormMetadata')->getData(true);

        $this->updateCacheTimestamp();

        return empty($ormData) ? false : true;
    }

    public function rebuildScheduledJobs()
    {
        $metadata = $this->getContainer()->get('metadata');
        $entityManager = $this->getContainer()->get('entityManager');

        $jobs = $metadata->get(['entityDefs', 'ScheduledJob', 'jobs'], array());

        foreach ($jobs as $jobName => $defs) {
            if ($jobName && !empty($defs['isSystem']) && !empty($defs['scheduling'])) {
                if (!$entityManager->getRepository('ScheduledJob')->where(array(
                    'job' => $jobName,
                    'status' => 'Active',
                    'scheduling' => $defs['scheduling']
                ))->findOne()) {
                    $job = $entityManager->getRepository('ScheduledJob')->where(array(
                        'job' => $jobName
                    ))->findOne();
                    if ($job) {
                        $entityManager->removeEntity($job);
                    }
                    $name = $jobName;
                    if (!empty($defs['name'])) {
                        $name = $defs['name'];
                    }
                    $job = $entityManager->getEntity('ScheduledJob');
                    $job->set(array(
                        'job' => $jobName,
                        'status' => 'Active',
                        'scheduling' => $defs['scheduling'],
                        'isInternal' => true,
                        'name' => $name
                    ));
                    $entityManager->saveEntity($job);
                }
            }
        }
    }

    /**
     * Update cache timestamp
     *
     * @return bool
     */
    public function updateCacheTimestamp()
    {
        $this->getContainer()->get('config')->updateCacheTimestamp();
        $this->getContainer()->get('config')->save();
        return true;
    }

    protected function populateConfigParameters()
    {
        $config = $this->getContainer()->get('config');

        $pdo = $this->getContainer()->get('entityManager')->getPDO();
        $query = "SHOW VARIABLES LIKE 'ft_min_word_len'";
        $sth = $pdo->prepare($query);
        $sth->execute();

        $fullTextSearchMinLength = null;
        if ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            if (isset($row['Value'])) {
                $fullTextSearchMinLength = intval($row['Value']);
            }
        }

        $config->set('fullTextSearchMinLength', $fullTextSearchMinLength);

        $config->save();
    }
}
