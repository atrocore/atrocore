<?php

declare(strict_types=1);

namespace Treo\Repositories;

use Espo\Repositories\Attachment as Base;
use Espo\ORM\Entity;
use Treo\Core\Utils\Util;
use Treo\Core\FilePathBuilder;
use Treo\Core\FileStorage\Storages\UploadDir;

/**
 * Class Attachment
 *
 * @package Treo\Repositories
 */
class Attachment extends Base
{
    /**
     * @param Entity $entity
     * @param null   $role
     *
     * @return |null
     * @throws \Espo\Core\Exceptions\Error
     */
    public function getCopiedAttachment(Entity $entity, $role = null)
    {
        $attachment = $this->get();

        $attachment->set(
            [
                'sourceId'        => $entity->getSourceId(),
                'name'            => $entity->get('name'),
                'type'            => $entity->get('type'),
                'size'            => $entity->get('size'),
                'role'            => $entity->get('role'),
                'storageFilePath' => $entity->get('storageFilePath'),
            ]
        );

        if ($role) {
            $attachment->set('role', $role);
        }

        $this->save($attachment);

        return $attachment;
    }

    /**
     * @param Entity $entity
     *
     * @return string
     */
    public function copy(Entity $entity): string
    {
        $source = $this->where(["id" => $entity->get('sourceId')])->findOne();

        $sourcePath = $this->getFilePath($source);
        $destPath = $this->getDestPath(FilePathBuilder::UPLOAD);
        $fullDestPath = UploadDir::BASE_PATH . $destPath;

        if ($this->getFileManager()->copy($sourcePath, $fullDestPath, false, null, true)) {
            return $destPath;
        }

        return '';
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @return mixed
     * @throws \Espo\Core\Exceptions\Error
     */
    public function save(Entity $entity, array $options = [])
    {
        $isNew = $entity->isNew();

        if ($isNew) {
            if (!$entity->has("id")) {
                $entity->id = Util::generateId();
            }
            $storeResult = false;

            if (!empty($entity->id) && $entity->has('contents')) {
                $contents = $entity->get('contents');
                if ($entity->get('role') === "Attachment") {
                    $temp = $this->getFileManager()->createOnTemp($contents);
                    if ($temp) {
                        $entity->set("tmpPath", $temp);
                        $storeResult = true;
                    }
                } else {
                    $storeResult = $this->getFileStorageManager()->putContents($entity, $contents);
                }
                if ($storeResult === false) {
                    throw new \Espo\Core\Exceptions\Error("Could not store the file");
                }
            }
        }

        $result = parent::save($entity, $options);

        return $result;
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    public function moveFromTmp(Entity $entity)
    {
        $destPath = $this->getDestPath(FilePathBuilder::UPLOAD);
        $fullPath = UploadDir::BASE_PATH . $destPath . "/" . $entity->get('name');

        if ($this->getFileManager()->move($entity->get('tmpPath'), $fullPath, false)) {
            $entity->set("tmpPath", null);
            $entity->set("storageFilePath", $destPath);

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        // call parent
        parent::init();

        $this->addDependency('fileStorageManager');
        $this->addDependency('filePathBuilder');
        $this->addDependency('fileManager');
    }

    /**
     * @inheritdoc
     */
    protected function getFileStorageManager()
    {
        return $this->getInjection('fileStorageManager');
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

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getDestPath(string $type): string
    {
        return $this->getPathBuilder()->createPath($type);
    }
}
