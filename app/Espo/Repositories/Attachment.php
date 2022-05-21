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

declare(strict_types=1);

namespace Espo\Repositories;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\InternalServerError;
use Espo\Core\Utils\Config;
use Espo\Entities\Attachment as AttachmentEntity;
use Espo\ORM\Entity;
use Espo\Core\ORM\Repositories\RDB;
use Espo\Core\FilePathBuilder;
use Espo\Core\Utils\Util;

/**
 * Class Attachment
 */
class Attachment extends RDB
{
    /**
     * @inheritDoc
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (empty($entity->get('storage'))) {
            $entity->set('storage', $this->getConfig()->get('defaultFileStorage', 'UploadDir'));
        }

        if (!$entity->isNew() && $entity->get('sourceId')) {
            $this->copyFile($entity);
        }
    }

    public function isPrivate(Entity $entity): bool
    {
        return $entity->isPrivate();
    }

    /**
     * @param Entity $entity
     * @param null   $role
     *
     * @return Entity
     */
    public function getCopiedAttachment(Entity $entity, $role = null)
    {
        $attachment = $this->get();

        $attachment->set(
            [
                'sourceId'         => $entity->getSourceId(),
                'name'             => $entity->get('name'),
                'type'             => $entity->get('type'),
                'size'             => $entity->get('size'),
                'role'             => $entity->get('role'),
                'storageFilePath'  => $entity->get('storageFilePath'),
                'storageThumbPath' => $entity->get('storageThumbPath'),
                'relatedType'      => $entity->get('relatedType'),
                'relatedId'        => $entity->get('relatedId'),
                'md5'              => $entity->get('md5')
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
        $fullDestPath = $this->getConfig()->get('filesPath', 'upload/files/') . $destPath;

        if ($this->getFileManager()->copy($sourcePath, $fullDestPath, false, null, true)) {
            return $destPath;
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public function save(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            if (!$entity->has("id")) {
                $entity->id = Util::generateId();
            }
            if (!empty($entity->id) && $entity->has('contents')) {
                $entity->set("storageFilePath", $this->getDestPath(FilePathBuilder::UPLOAD));
                $entity->set("storageThumbPath", $this->getDestPath(FilePathBuilder::UPLOAD));

                $storeResult = $this->getFileStorageManager()->putContents($entity, $entity->get('contents'));
                if ($storeResult === false) {
                    throw new Error("Could not store the file");
                }
            }
        }

        return parent::save($entity, $options);
    }

    /**
     * @param AttachmentEntity|string $attachment
     *
     * @return array
     */
    public function getAttachmentPathsData($attachment): array
    {
        if (is_string($attachment)) {
            $attachment = $this->get($attachment);
        }

        $result = [
            'download' => null,
            'thumbs'   => [],
        ];

        if (!empty($attachment)) {
            $result['download'] = $this->getFileStorageManager()->getDownloadUrl($attachment);
            $result['thumbs'] = $this->getFileStorageManager()->getThumbs($attachment);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function remove(Entity $entity, array $options = [])
    {
        $result = parent::remove($entity, $options);

        $duplicateCount = $this->where(['OR' => [['sourceId' => $entity->getSourceId()], ['id' => $entity->getSourceId()]]])->count();
        if ($duplicateCount === 0) {
            // unlink file
            $this->getFileStorageManager()->unlink($entity);

            // remove record from DB table
            $this->deleteFromDb($entity->get('id'));
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('config');
        $this->addDependency('language');
        $this->addDependency('fileStorageManager');
        $this->addDependency('filePathBuilder');
        $this->addDependency('fileManager');
        $this->addDependency('Thumbnail');
    }

    /**
     * @param Entity $entity
     *
     * @return string|null
     */
    public function getContents(Entity $entity): ?string
    {
        return $this->getFileStorageManager()->getContents($entity);
    }

    /**
     * @param Entity $entity
     *
     * @return string|null
     */
    public function getFilePath(Entity $entity): ?string
    {
        return $this->getFileStorageManager()->getLocalFilePath($entity);
    }

    /**
     * @param Entity $entity
     *
     * @return string|null
     */
    public function getDownloadUrl(Entity $entity): ?string
    {
        return $this->getFileStorageManager()->getDownloadUrl($entity);
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public function getDestPath(string $type): string
    {
        return $this->getPathBuilder()->createPath($type);
    }

    /**
     * @return Config
     */
    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    /**
     * @param string $key
     * @param string $label
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $key, string $label, $scope = 'Global'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    /**
     * @return \Treo\Core\FileStorage\Manager
     */
    protected function getFileStorageManager()
    {
        return $this->getInjection('fileStorageManager');
    }

    /**
     * @return FilePathBuilder
     */
    protected function getPathBuilder()
    {
        return $this->getInjection('filePathBuilder');
    }

    /**
     * @return \Treo\Core\Utils\File\Manager
     */
    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }

    /**
     * @param Entity $entity
     *
     * @throws InternalServerError
     */
    protected function copyFile(Entity $entity): void
    {
        $path = $this->copy($entity);
        if (!$path) {
            throw new InternalServerError($this->translate("Can't copy file", 'exceptions', 'Global'));
        }

        $entity->set(
            [
                'sourceId'        => null,
                'storageFilePath' => $path,
            ]
        );
    }
}
