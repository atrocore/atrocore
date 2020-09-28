<?php

namespace Espo\Core\Utils;

class Module
{
    private $fileManager;

    private $useCache;

    private $unifier;

    protected $data = null;

    protected $cacheFile = 'data/cache/application/modules.php';

    protected $paths = array(
        'corePath' => CORE_PATH . '/Espo/Resources/module.json',
        'modulePath' => CORE_PATH . '/Espo/Modules/{*}/Resources/module.json',
        'customPath' => 'custom/Espo/Custom/Resources/module.json',
    );

    public function __construct(File\Manager $fileManager, $useCache = false)
    {
        $this->fileManager = $fileManager;

        $this->unifier = new \Espo\Core\Utils\File\FileUnifier($this->fileManager);

        $this->useCache = $useCache;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }

    protected function getUnifier()
    {
        return $this->unifier;
    }

    public function get($key = '', $returns = null)
    {
        if (!isset($this->data)) {
            $this->init();
        }

        if (empty($key)) {
            return $this->data;
        }

        return Util::getValueByKey($this->data, $key, $returns);
    }

    public function getAll()
    {
        return $this->get();
    }

    protected function init()
    {
        if (file_exists($this->cacheFile) && $this->useCache) {
            $this->data = $this->getFileManager()->getPhpContents($this->cacheFile);
        } else {
            $this->data = $this->getUnifier()->unify($this->paths, true);

            if ($this->useCache) {
                $result = $this->getFileManager()->putPhpContents($this->cacheFile, $this->data);
                if ($result == false) {
                    throw new \Espo\Core\Exceptions\Error('Module - Cannot save unified modules.');
                }
            }
        }
    }
}