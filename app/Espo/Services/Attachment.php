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
 */

namespace Espo\Services;

use Espo\Core\Exceptions\NotFound;
use \Espo\ORM\Entity;

use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\Error;
use Treo\Core\EventManager\Event;
use Treo\Core\FilePathBuilder;
use Treo\Core\Utils\Util;

class Attachment extends Record
{
    protected const CHUNKS_DIR = 'data/chunks/';

    protected $notFilteringAttributeList = ['contents'];

    protected $attachmentFieldTypeList = ['file', 'image', 'attachmentMultiple', 'asset'];

    protected $inlineAttachmentFieldTypeList = ['text', 'wysiwyg', 'wysiwygMultiLang'];

    public function upload($fileData)
    {
        if (!$this->getAcl()->checkScope('Attachment', 'create')) {
            throw new Forbidden();
        }

        $arr = explode(',', $fileData);
        if (count($arr) > 1) {
            list($prefix, $contents) = $arr;
            $contents = base64_decode($contents);
        } else {
            $contents = '';
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment');
        $attachment->set('contents', $contents);
        $this->getEntityManager()->saveEntity($attachment);

        return $attachment;
    }

    /**
     * @param \stdClass $attachment
     *
     * @return bool
     */
    public function createChunks(\stdClass $attachment): array
    {
        $this->clearTrash();

        $contents = $this->parseInputFileContent($attachment->piece);

        $path = self::CHUNKS_DIR . $attachment->chunkId;
        if (!file_exists($path)) {
            $path .= '/' . time();
            mkdir($path, 0777, true);
        } else {
            foreach (Util::scanDir($path) as $dir) {
                $path .= '/' . $dir;
                break;
            }
        }

        file_put_contents($path . '/' . $attachment->start, $contents);

        return ['chunks' => Util::scanDir($path)];
    }

    /**
     * @param \stdClass $attachment
     *
     * @return Entity
     * @throws Error
     * @throws Forbidden
     * @throws NotFound
     */
    public function createByChunks(\stdClass $attachment): Entity
    {
        $attachment = $this
            ->dispatchEvent('beforeCreateEntity', new Event(['attachment' => $attachment]))
            ->getArgument('attachment');

        $this->clearTrash();

        $dirPath = self::CHUNKS_DIR . $attachment->chunkId . '/';

        if (!file_exists($dirPath) || !is_dir($dirPath)) {
            throw new NotFound();
        }

        foreach (Util::scanDir($dirPath) as $dir) {
            $dirPath .= $dir . '/';
            break;
        }

        $files = Util::scanDir($dirPath);
        sort($files);

        $destPath = $this->getRepository()->getDestPath(FilePathBuilder::UPLOAD);

        $fullPath = $this->getConfig()->get('filesPath', 'upload/files/') . $destPath;
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $filePath = $fullPath . "/" . $attachment->name;

        $md5 = '';
        file_put_contents($filePath, '');
        foreach ($files as $file) {
            $md5 = md5($md5 . $file);
            file_put_contents($filePath, file_get_contents($dirPath . $file), FILE_APPEND);
        }

        $attachment->md5 = $md5;
        $attachment->storageFilePath = $destPath;
        $attachment->storageThumbPath = $this->getRepository()->getDestPath(FilePathBuilder::UPLOAD);

        $duplicateParam = $this->getConfig()->get('attachmentDuplicates', 'notAllowByContent');
        if ($duplicateParam == 'notAllowByContent') {
            $entity = $this->getRepository()->where(['md5' => $attachment->md5, 'tmpPath' => null])->findOne();
        } elseif ($duplicateParam == 'notAllowByContentAndName') {
            $entity = $this->getRepository()->where(['md5' => $attachment->md5, 'tmpPath' => null, 'name' => $attachment->name])->findOne();
        }

        if (empty($entity)) {
            $entity = parent::createEntity(clone $attachment);
        }

        // create thumbnails
        $this->createThumbnails($entity);

        $entity->set('pathsData', $this->getRepository()->getAttachmentPathsData($entity));

        return $this
            ->dispatchEvent('afterCreateEntity', new Event(['attachment' => $attachment, 'entity' => $entity]))
            ->getArgument('entity');
    }

    /**
     * @param \stdClass $attachment
     *
     * @return mixed
     *
     * @throws BadRequest
     * @throws Error
     * @throws Forbidden
     */
    public function createEntity($attachment)
    {
        $this->clearTrash();

        if (!empty($attachment->file)) {
            $attachment->contents = $this->parseInputFileContent($attachment->file);

            $relatedEntityType = null;
            $field = null;
            $role = 'Attachment';
            if (isset($attachment->parentType)) {
                $relatedEntityType = $attachment->parentType;
            } else {
                if (isset($attachment->relatedType)) {
                    $relatedEntityType = $attachment->relatedType;
                }
            }
            if (isset($attachment->field)) {
                $field = $attachment->field;
            }
            if (isset($attachment->role)) {
                $role = $attachment->role;
            }
            if (!$relatedEntityType || !$field) {
                throw new BadRequest("Params 'field' and 'parentType' not passed along with 'file'.");
            }

            $fieldType = $this->getMetadata()->get(['entityDefs', $relatedEntityType, 'fields', $field, 'type']);
            if (!$fieldType) {
                throw new Error("Field '{$field}' does not exist.");
            }

            if (
                !$this->getAcl()->checkScope($relatedEntityType, 'create')
                && !$this->getAcl()->checkScope($relatedEntityType, 'edit')
            ) {
                throw new Forbidden("No access to " . $relatedEntityType . ".");
            }

            if (in_array($field, $this->getAcl()->getScopeForbiddenFieldList($relatedEntityType, 'edit'))) {
                throw new Forbidden("No access to field '" . $field . "'.");
            }

            $size = mb_strlen($attachment->contents, '8bit');

            if ($role === 'Attachment') {
                if (!in_array($fieldType, $this->attachmentFieldTypeList)) {
                    throw new Error("Field type '{$fieldType}' is not allowed for attachment.");
                }
                $maxSize = $this->getMetadata()->get(['entityDefs', $relatedEntityType, 'fields', $field, 'maxFileSize']);
                if (!$maxSize) {
                    $maxSize = $this->getConfig()->get('attachmentUploadMaxSize');
                }
                if ($maxSize) {
                    if ($size > $maxSize * 1024 * 1024) {
                        throw new Error("File size should not exceed {$maxSize}Mb.");
                    }
                }

            } else {
                if ($role === 'Inline Attachment') {
                    if (!in_array($fieldType, $this->inlineAttachmentFieldTypeList)) {
                        throw new Error("Field '{$field}' is not allowed to have inline attachment.");
                    }
                    $inlineAttachmentUploadMaxSize = $this->getConfig()->get('inlineAttachmentUploadMaxSize');
                    if ($inlineAttachmentUploadMaxSize) {
                        if ($size > $inlineAttachmentUploadMaxSize * 1024 * 1024) {
                            throw new Error("File size should not exceed {$inlineAttachmentUploadMaxSize}Mb.");
                        }
                    }
                } else {
                    throw new BadRequest("Not supported attachment role.");
                }
            }
        }

        if (empty($attachment->contents)) {
            throw new BadRequest($this->getInjection('language')->translate('fileUploadingFailed', 'exceptions', 'Attachment'));
        }

        $attachment->md5 = md5($attachment->contents);

        if (empty($attachment->size)) {
            $attachment->size = mb_strlen($attachment->contents);
        }

        $duplicateParam = $this->getConfig()->get('attachmentDuplicates', 'notAllowByContent');

        if ($duplicateParam == 'notAllowByContent') {
            $entity = $this->getRepository()->where(['md5' => $attachment->md5, 'tmpPath' => null])->findOne();
        } elseif ($duplicateParam == 'notAllowByContentAndName') {
            $entity = $this->getRepository()->where(['md5' => $attachment->md5, 'tmpPath' => null, 'name' => $attachment->name])->findOne();
        }

        if (empty($entity)) {
            $entity = parent::createEntity(clone $attachment);

            if (!empty($attachment->file)) {
                $entity->clear('contents');
            }
        }

        // create thumbnails
        $this->createThumbnails($entity);

        $entity->set('pathsData', $this->getRepository()->getAttachmentPathsData($entity));

        return $entity;
    }

    /**
     * Remove old chunk dirs
     */
    public function clearTrash(): void
    {
        $checkDate = (new \DateTime())->modify('-1 day');

        // Remove old chunk dirs
        foreach (Util::scanDir(self::CHUNKS_DIR) as $chunkId) {
            $path = self::CHUNKS_DIR . '/' . $chunkId;
            foreach (Util::scanDir($path) as $timestamp) {
                if ($timestamp < $checkDate->getTimestamp()) {
                    Util::removeDir($path);
                    break 1;
                }
            }
        }

        // Remove old records from DB table
        $this
            ->getEntityManager()
            ->nativeQuery("DELETE FROM attachment WHERE created_at < '{$checkDate->format('Y-m-d H:i:s')}' AND tmp_path IS NOT NULL");
    }

    /**
     * @inheritDoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('pathsData', $this->getRepository()->getAttachmentPathsData($entity));
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $storage = $entity->get('storage');
        if ($storage && !$this->getMetadata()->get(['app', 'fileStorage', 'implementationClassNameMap', $storage])) {
            $entity->clear('storage');
        }
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $storage = $entity->get('storage');
        if ($storage && !$this->getMetadata()->get(['app', 'fileStorage', 'implementationClassNameMap', $storage])) {
            $entity->clear('storage');
        }
    }

    /**
     * @param string $fileContent
     *
     * @return string
     */
    protected function parseInputFileContent(string $fileContent): string
    {
        $arr = explode(',', $fileContent);
        $contents = '';
        if (count($arr) > 1) {
            $contents = $arr[1];
        }

        return base64_decode($contents);
    }

    protected function createThumbnails(Entity $entity): void
    {
        if (in_array($entity->get('type'), $this->getMetadata()->get(['app', 'typesWithThumbnails'], []))) {
            $this->getInjection('queueManager')->push('Create thumbs', 'QueueManagerCreateThumbs', ['id' => $entity->get('id')], 1);
        }
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('queueManager');
    }
}

