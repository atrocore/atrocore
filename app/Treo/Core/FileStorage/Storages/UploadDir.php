<?php

namespace Treo\Core\FileStorage\Storages;

use Espo\Core\Exceptions\Error;
use Treo\Core\FilePathBuilder;
use Treo\Entities\Attachment;

/**
 * Class UploadDir
 *
 * @package Treo\Core\FileStorage\Storages
 */
class UploadDir extends Base
{
    const BASE_PATH = "data/upload/files/";
    const BASE_THUMB_PATH = "data/upload/thumbs/";
    /**
     * @var array
     */
    protected $dependencyList = ['fileManager', 'filePathBuilder'];

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    public function unlink(Attachment $attachment)
    {
        return $this->getFileManager()->unlink($this->getFilePath($attachment));
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    public function isFile(Attachment $attachment)
    {
        return $this->getFileManager()->isFile($this->getFilePath($attachment));
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    public function getContents(Attachment $attachment)
    {
        return $this->getFileManager()->getContents($this->getFilePath($attachment));
    }

    /**
     * @param Attachment $attachment
     * @param            $contents
     *
     * @return mixed
     */
    public function putContents(Attachment $attachment, $contents)
    {
        return $this->getFileManager()->putContents($this->getFilePath($attachment), $contents);
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed|string
     */
    public function getLocalFilePath(Attachment $attachment)
    {
        return $this->getFilePath($attachment);
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed|void
     * @throws Error
     */
    public function getDownloadUrl(Attachment $attachment)
    {
        throw new Error();
    }

    /**
     * @param Attachment $attachment
     *
     * @return bool|mixed
     */
    public function hasDownloadUrl(Attachment $attachment)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('entityManager');
    }

    /**
     * @param Attachment $attachment
     *
     * @return string
     */
    protected function getFilePath(Attachment $attachment): string
    {
        $storage = $attachment->get('storageFilePath');

        if (!$storage) {
            $storage = $this->getPathBuilder()->createPath(FilePathBuilder::UPLOAD);
            $attachment->set('storageFilePath', $storage);
        }

        // prepare path
        $path = self::BASE_PATH . "{$storage}/" . $attachment->get('name');

        // move old files to new dirs if it needs
        if (!file_exists($path) && !$attachment->isNew()) {
            // prepare id
            $id = $attachment->get('id');

            // prepare old path
            $oldPath = "data/upload/$id";

            if (file_exists($oldPath) && $this->getFileManager()->move($oldPath, $path)) {
                $this
                    ->getInjection('entityManager')
                    ->nativeQuery("UPDATE attachment SET storage='UploadDir', storage_file_path='$storage' WHERE id='$id'");
            }
        }

        return $path;
    }

    /**
     * @return mixed
     */
    protected function getPathBuilder()
    {
        return $this->getInjection('filePathBuilder');
    }

    /**
     * @return mixed
     */
    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }
}
