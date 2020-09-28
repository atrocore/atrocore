<?php

namespace Treo\Core\FileStorage;

use Espo\Core\Exceptions\Error;
use Treo\Core\Container;
use Treo\Entities\Attachment;

/**
 * Class Manager
 * @package Treo\Core\FileStorage
 */
class Manager
{
    /**
     * @var array
     */
    private $implementations = [];

    /**
     * @var array
     */
    private $implementationClassNameMap = [];

    /**
     * @var Container
     */
    private $container;

    /**
     * Manager constructor.
     * @param array $implementationClassNameMap
     * @param $container
     */
    public function __construct(array $implementationClassNameMap, $container)
    {
        $this->implementationClassNameMap = $implementationClassNameMap;
        $this->container = $container;
    }

    /**
     * @param null $storage
     * @return mixed
     * @throws Error
     */
    protected function getImplementation($storage = null)
    {
        if (!$storage) {
            $storage = 'UploadDir';
        }

        if (array_key_exists($storage, $this->implementations)) {
            return $this->implementations[$storage];
        }

        if (!array_key_exists($storage, $this->implementationClassNameMap)) {
            throw new Error("FileStorageManager: Unknown storage '{$storage}'");
        }
        $className = $this->implementationClassNameMap[$storage];

        $implementation = new $className();
        foreach ($implementation->getDependencyList() as $dependencyName) {
            $implementation->inject($dependencyName, $this->container->get($dependencyName));
        }
        $this->implementations[$storage] = $implementation;

        return $implementation;
    }

    /**
     * @param Attachment $attachment
     * @return mixed
     * @throws Error
     */
    public function isFile(Attachment $attachment)
    {
        $implementation = $this->getImplementation($attachment->get('storage'));
        return $implementation->isFile($attachment);
    }

    /**
     * @param Attachment $attachment
     * @return mixed
     * @throws Error
     */
    public function getContents(Attachment $attachment)
    {
        $implementation = $this->getImplementation($attachment->get('storage'));
        return $implementation->getContents($attachment);
    }

    /**
     * @param Attachment $attachment
     * @param $contents
     * @return mixed
     * @throws Error
     */
    public function putContents(Attachment $attachment, $contents)
    {
        $implementation = $this->getImplementation($attachment->get('storage'));
        return $implementation->putContents($attachment, $contents);
    }

    /**
     * @param Attachment $attachment
     * @return mixed
     * @throws Error
     */
    public function unlink(Attachment $attachment)
    {
        $implementation = $this->getImplementation($attachment->get('storage'));
        return $implementation->unlink($attachment);
    }

    /**
     * @param Attachment $attachment
     * @return mixed
     * @throws Error
     */
    public function getLocalFilePath(Attachment $attachment)
    {
        $implementation = $this->getImplementation($attachment->get('storage'));
        return $implementation->getLocalFilePath($attachment);
    }

    /**
     * @param Attachment $attachment
     * @return mixed
     * @throws Error
     */
    public function hasDownloadUrl(Attachment $attachment)
    {
        $implementation = $this->getImplementation($attachment->get('storage'));
        return $implementation->hasDownloadUrl($attachment);
    }

    /**
     * @param Attachment $attachment
     * @return mixed
     * @throws Error
     */
    public function getDownloadUrl(Attachment $attachment)
    {
        $implementation = $this->getImplementation($attachment->get('storage'));
        return $implementation->getDownloadUrl($attachment);
    }
}
