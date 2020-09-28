<?php

namespace Espo\Core\Utils\Metadata;

use Espo\Core\Utils\Util;

class OrmMetadata
{
    protected $data = array();

    protected $cacheFile = 'data/cache/application/ormMetadata.php';

    protected $metadata;

    protected $fileManager;

    protected $config;

    protected $useCache;

    public function __construct(\Espo\Core\Utils\Metadata $metadata, \Espo\Core\Utils\File\Manager $fileManager, $config)
    {
        $this->metadata = $metadata;
        $this->fileManager = $fileManager;

        $this->useCache = false;
        if ($config instanceof \Espo\Core\Utils\Config) {
            $this->config = $config;
            $this->useCache = $this->config->get('useCache', false);
        } elseif (is_bool($config)) {
            $this->useCache = $config;
        }
    }

    protected function getConverter()
    {
        if (!isset($this->converter)) {
            $this->converter = new \Espo\Core\Utils\Database\Converter($this->metadata, $this->fileManager, $this->config);
        }

        return $this->converter;
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    public function clearData()
    {
        $this->ormData = null;
    }

    public function getData($reload = false)
    {
        if (!empty($this->ormData) && !$reload) {
            return $data;
        }

        if (!file_exists($this->cacheFile) || !$this->useCache || $reload) {
            $this->data = $this->getConverter()->process();

            if ($this->useCache) {
                $result = $this->getFileManager()->putPhpContents($this->cacheFile, $this->data);
                if ($result == false) {
                    throw new \Espo\Core\Exceptions\Error('OrmMetadata::getData() - Cannot save ormMetadata to cache file');
                }
            }
        }

        if (empty($this->data)) {
            $this->data = $this->getFileManager()->getPhpContents($this->cacheFile);
        }

        return $this->data;
    }

    public function get($key = null, $default = null)
    {
        $result = Util::getValueByKey($this->getData(), $key, $default);
        return $result;
    }
}