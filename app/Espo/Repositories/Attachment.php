<?php

namespace Espo\Repositories;

use Espo\ORM\Entity;

use Espo\Core\Utils\Util;

class Attachment extends \Espo\Core\ORM\Repositories\RDB
{
    protected function init()
    {
        parent::init();
        $this->addDependency('container');
        $this->addDependency('config');
    }

    protected function getFileStorageManager()
    {
        return $this->getInjection('container')->get('fileStorageManager');
    }

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    public function beforeSave(Entity $entity, array $options = array())
    {
        parent::beforeSave($entity, $options);

        $storage = $entity->get('storage');
        if (!$storage) {
            $entity->set('storage', $this->getConfig()->get('defaultFileStorage', null));
        }

        if ($entity->isNew()) {
            if (!$entity->has('size') && $entity->has('contents')) {
                $entity->set('size', mb_strlen($entity->get('contents')));
            }
        }
    }

    protected function afterRemove(Entity $entity, array $options = array())
    {
        parent::afterRemove($entity, $options);

        $duplicateCount = $this->where([
            'OR' => [
                [
                    'sourceId' => $entity->getSourceId()
                ],
                [
                    'id' => $entity->getSourceId()
                ]
            ],
        ])->count();

        if ($duplicateCount === 0) {
            $this->getFileStorageManager()->unlink($entity);
        }
    }

    public function getContents(Entity $entity)
    {
        return $this->getFileStorageManager()->getContents($entity);
    }

    public function getFilePath(Entity $entity)
    {
        return $this->getFileStorageManager()->getLocalFilePath($entity);
    }

    public function hasDownloadUrl(Entity $entity)
    {
        return $this->getFileStorageManager()->hasDownloadUrl($entity);
    }

    public function getDownloadUrl(Entity $entity)
    {
        return $this->getFileStorageManager()->getDownloadUrl($entity);
    }
}
