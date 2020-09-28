<?php

namespace Espo\Core\Utils\File;
use \Espo\Core\Utils\Util;

class ClassParser
{
    private $fileManager;

    private $config;

    private $metadata;

    protected $cacheFile = null;

    protected $allowedMethods = array(
        'run',
    );

    public function __construct(\Espo\Core\Utils\File\Manager $fileManager, \Espo\Core\Utils\Config $config, \Espo\Core\Utils\Metadata $metadata)
    {
        $this->fileManager = $fileManager;
        $this->config = $config;
        $this->metadata = $metadata;
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    protected function getMetadata()
    {
        return $this->metadata;
    }

    public function setAllowedMethods($methods)
    {
        $this->allowedMethods = $methods;
    }

    /**
     * Return path data of classes
     *
     * @param  string  $cacheFile full path for a cache file, ex. data/cache/application/entryPoints.php
     * @param  string | array $paths in format array(
     *    'corePath' => '',
     *    'modulePath' => '',
     *    'customPath' => '',
     * );
     * @return array
     */
    public function getData($paths, $cacheFile = false)
    {
        $data = null;

        if (is_string($paths)) {
            $paths = array(
                'corePath' => $paths,
            );
        }

        if ($cacheFile && file_exists($cacheFile) && $this->getConfig()->get('useCache')) {
            $data = $this->getFileManager()->getPhpContents($cacheFile);
        } else {
            // load Treo
            $data = $this->getClassNameHash(str_replace(CORE_PATH . '/Espo', CORE_PATH . '/Treo', $paths['corePath']));

            // load Espo
            $data = array_merge($data, $this->getClassNameHash($paths['corePath']));

            // load modules
            if (isset($paths['modulePath'])) {
                foreach ($this->getMetadata()->getModules() as $module) {
                    $data = array_merge(
                        $data,
                        $module->getClassNameHash(str_replace(CORE_PATH . "/Espo/Modules/{*}/", '', $paths['modulePath']))
                    );
                }
            }

            // load custom
            if (isset($paths['customPath'])) {
                $data = array_merge($data, $this->getClassNameHash($paths['customPath']));
            }

            if ($cacheFile && $this->getConfig()->get('useCache')) {
                $result = $this->getFileManager()->putPhpContents($cacheFile, $data);
                if ($result == false) {
                    throw new \Espo\Core\Exceptions\Error();
                }
            }
        }

        return $data;
    }

    protected function getClassNameHash($dirs)
    {
        if (is_string($dirs)) {
            $dirs = (array) $dirs;
        }

        $data = array();
        foreach ($dirs as $dir) {
            if (file_exists($dir)) {
                $fileList = $this->getFileManager()->getFileList($dir, false, '\.php$', true);

                foreach ($fileList as $file) {
                    $filePath = Util::concatPath($dir, $file);
                    $className = Util::getClassName($filePath);
                    $fileName = $this->getFileManager()->getFileName($filePath);

                    $scopeName = ucfirst($fileName);
                    $normalizedScopeName = Util::normilizeScopeName($scopeName);

                    if (empty($this->allowedMethods)) {
                        $data[$normalizedScopeName] = $className;
                        continue;
                    }

                    foreach ($this->allowedMethods as $methodName) {
                        if (method_exists($className, $methodName)) {
                            $data[$normalizedScopeName] = $className;
                        }
                    }

                }
            }
        }

        return $data;
    }

}