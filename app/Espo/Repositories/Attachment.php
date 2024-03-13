<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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
 */

declare(strict_types=1);

namespace Espo\Repositories;

use Atro\Core\AssetValidator;
use Atro\Core\PseudoTransactionManager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\FilePathBuilder;
use Espo\Core\ORM\Repositories\RDB;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;
use Espo\Entities\Attachment as AttachmentEntity;
use Espo\ORM\Entity;
use Espo\ORM\Entity as Asset;

/**
 * Class Attachment
 */
class Attachment extends RDB
{
    /**
     * @param Entity $entity
     *
     * @return \Atro\Repositories\Asset|null
     */
    public function getAsset(Entity $entity): ?Asset
    {
        return $this->getEntityManager()->getRepository('Asset')->where(['fileId' => $entity->get('id')])->findOne(["withDeleted" => $entity->get('deleted')]);
    }

    protected function isPdf(Entity $entity): bool
    {
        if (empty($entity->get('name'))) {
            return false;
        }

        $parts = explode('.', $entity->get('name'));

        return strtolower(array_pop($parts)) === 'pdf';
    }

    /**
     * Create asset if it needs
     *
     * @param Entity      $attachment
     * @param bool        $skipValidation
     * @param string|null $type
     *
     * @throws Error
     * @throws \Throwable
     */
    public function createAsset(Entity $attachment, bool $skipValidation = false, string $type = null)
    {
        if (!empty($this->where(['fileId' => $attachment->get('id')])->findOne())) {
            return;
        }

        if ($type === null) {
            $type = $this->getMetadata()->get(['entityDefs', $attachment->get('relatedType'), 'fields', $attachment->get('field'), 'assetType']);
        }

        $asset = $this->getEntityManager()->getEntity('Asset');
        $asset->set('name', $attachment->get('name'));
        $asset->set('private', $this->getConfig()->get('isUploadPrivate', true));
        $asset->set('fileId', $attachment->get('id'));
        if (!empty($type)) {
            $options = $this->getMetadata()->get(['entityDefs', 'Asset', 'fields', 'type', 'options'], []);
            $optionsIds = $this->getMetadata()->get(['entityDefs', 'Asset', 'fields', 'type', 'optionsIds'], []);

            $key = array_search($type, $options);
            if ($key !== false && isset($optionsIds[$key])) {
                $type = $optionsIds[$key];
            }

            $asset->set('type', [$type]);
            if (!$skipValidation) {
                try {
                    $this->getInjection(AssetValidator::class)->validate($asset);
                } catch (Throwable $exception) {
                    $this->getEntityManager()->removeEntity($attachment);
                    throw $exception;
                }
            }
        }

        $this->getEntityManager()->saveEntity($asset);
    }

    protected function getPseudoTransactionManager(): PseudoTransactionManager
    {
        return $this->getInjection('pseudoTransactionManager');
    }

    /**
     * @param Entity $entity
     * @param string $path
     *
     * @return mixed
     * @throws Error
     */
    public function updateStorage(Entity $entity, string $path)
    {
        $entity->set("storageFilePath", $path);

        return $this->save($entity);
    }

    /**
     * @param Entity $attachment
     * @param string $newFileName
     *
     * @return bool
     * @throws Error
     */
    public function renameFile(Entity $attachment, string $newFile): bool
    {
        $path = $this->getFilePath($attachment);

        $pathParts = explode('/', $path);
        $fileName = array_pop($pathParts);

        if ($fileName == $newFile) {
            return true;
        }

        $newFileParts = explode('.', $newFile);
        array_pop($newFileParts);

        $attachment->setName(implode('.', $newFileParts));

        if ($this->getFileManager()->move($path, $this->getFilePath($attachment))) {
            return $this->save($attachment) ? true : false;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $pattern = '/^([^ ]+[^\"\/\x00\r\n\t\:\*\?"<>\|\cA-\cZ]+(\.[^\. ]+)?[^ ]+){1,254}$/';
        if ($entity->isAttributeChanged('name') && !preg_match($pattern, (string)$entity->get('name'))) {
            throw new BadRequest(sprintf($this->translate('suchFileNameNotValid', 'exceptions', 'Asset'), (string)$entity->get('name')));
        }

        if (empty($entity->get('storage'))) {
            $entity->set('storage', $this->getConfig()->get('defaultFileStorage', 'UploadDir'));
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        // if uploaded new attachment with previous name
        $res = $this
            ->where(
                [
                    "relatedId"       => $entity->get("relatedId"),
                    "relatedType"     => $entity->get("relatedType"),
                    "storageFilePath" => $entity->get("storageFilePath"),
                    "name"            => $entity->get("name"),
                    "deleted"         => 0
                ]
            )
            ->count();

        if (!$res) {
            parent::afterRemove($entity, $options);
        }

        if ($this->isPdf($entity)) {
            $dirPath = $this->getConfig()->get('filesPath', 'upload/files/') . $entity->getStorageFilePath();

            $this->getFileManager()->unlink($dirPath . '/page-1.png');
        }

        if ($this->getMetadata()->get(['entityDefs', 'Asset', 'fields', 'file', 'required'], false)) {
            $offset = 0;
            $limit = 20;

            while(true) {
                $assets = $this
                    ->getEntityManager()
                    ->getRepository('Asset')
                    ->where([
                        'fileId' => $entity->id
                    ])
                    ->limit($offset, $limit)
                    ->find();

                if (count($assets) == 0) {
                    break;
                }

                foreach ($assets as $asset) {
                    $this->getPseudoTransactionManager()->pushDeleteEntityJob('Asset', $asset->id);
                }

                $offset += $limit;
            }
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

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('config');
        $this->addDependency('language');
        $this->addDependency('fileStorageManager');
        $this->addDependency('filePathBuilder');
        $this->addDependency('fileManager');
        $this->addDependency('thumbnail');
        $this->addDependency(AssetValidator::class);
        $this->addDependency('pseudoTransactionManager');
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
     * @return \Espo\Core\FileStorage\Manager
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

    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }
}
