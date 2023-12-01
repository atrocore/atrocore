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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\Core\FilePathBuilder;

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

        $chunks = Util::scanDir($path);
        sort($chunks);

        $result = [
            'chunks' => $chunks
        ];

        if (count($chunks) === $attachment->piecesCount) {
            $this->prepareAttachmentFilePath($attachment);

            // create file from chunks
            $file = fopen($attachment->fileName, 'a+');
            foreach ($chunks as $chunk) {
                fwrite($file, file_get_contents($path . '/' . $chunk));
            }

            try {
                $attachmentEntity = $this->createEntity($attachment);
            } catch (\Throwable $e) {
                if (file_exists($attachment->fileName)) {
                    unlink($attachment->fileName);
                }
                throw $e;
            }

            if (strpos($attachment->fileName, $attachmentEntity->get('storageFilePath')) === false) {
                if (file_exists($attachment->fileName)) {
                    unlink($attachment->fileName);
                }
            }

            $result['attachment'] = $attachmentEntity->toArray();

            // remove chunks
            Util::removeDir(self::CHUNKS_DIR . $attachment->chunkId);
        }

        return $result;
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
        $ext = pathinfo($attachment->name, PATHINFO_EXTENSION);

        if (!in_array(strtolower($ext), $this->getConfig()->get('whitelistedExtensions'))) {
            throw new BadRequest(sprintf($this->getInjection('language')->translate('invalidFileExtension', 'exceptions', 'Attachment'), $ext));
        }

        $this->clearTrash();

        if (!empty($attachment->file)) {
            $attachment->contents = $this->parseInputFileContent($attachment->file);

            $relatedEntityType = null;
            if (property_exists($attachment, 'relatedType')) {
                $relatedEntityType = $attachment->relatedType;
            } elseif (property_exists($attachment, 'parentType')) {
                $relatedEntityType = $attachment->parentType;
            }

            if (!$relatedEntityType) {
                throw new BadRequest("Params 'relatedType' not passed along with 'file'.");
            }

            if (!$this->getAcl()->checkScope($relatedEntityType, 'create') && !$this->getAcl()->checkScope($relatedEntityType, 'edit')) {
                throw new Forbidden("No access to " . $relatedEntityType . ".");
            }
        }

        /**
         * Create file from content
         */
        if (!empty($attachment->contents)) {
            $this->prepareAttachmentFilePath($attachment);
            file_put_contents($attachment->fileName, $attachment->contents);
            unset($attachment->contents);
        }

        if (empty($attachment->fileName) || !file_exists($attachment->fileName)) {
            throw new Error('Attachment creating failed.');
        }

        $attachment->md5 = md5_file($attachment->fileName);
        $attachment->size = filesize($attachment->fileName);

        if (!property_exists($attachment, 'type')) {
            $attachment->type = mime_content_type($attachment->fileName);
        }

        if (empty($entity = $this->findAttachmentDuplicate($attachment))) {
            $entity = parent::createEntity(clone $attachment);

            if (!empty($attachment->file)) {
                $entity->clear('contents');
            }

            // create thumbnails
            $this->createThumbnails($entity);
        } else {
            unlink($attachment->fileName);
        }

        $entity->set('pathsData', $this->getRepository()->getAttachmentPathsData($entity));

        return $entity;
    }

    public function findAttachmentDuplicate(\stdClass $attachment): ?Entity
    {
        return null;
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
    }

    /**
     * @inheritDoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('pathsData', $this->getRepository()->getAttachmentPathsData($entity));

        $url = rtrim($this->getConfig()->get('siteUrl', ''), '/');
        $url .= '/';
        $url .= $entity->get('pathsData')['download'];
        $entity->set('url', $url);
    }

    protected function prepareAttachmentFilePath(\stdClass $attachment): void
    {
        $attachment->storageFilePath = $this->getRepository()->getDestPath(FilePathBuilder::UPLOAD);
        $attachment->storageThumbPath = $this->getRepository()->getDestPath(FilePathBuilder::UPLOAD);

        $fullPath = $this->getConfig()->get('filesPath', 'upload/files/') . $attachment->storageFilePath;
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $attachment->fileName = $fullPath . '/' . $attachment->name;
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
        // do not create thumbnails when import
        if (!empty($this->getMemoryStorage()->get('importJobId'))) {
            return;
        }

        if (!in_array($entity->get('type'), $this->getMetadata()->get(['app', 'typesWithThumbnails'], []))) {
            return;
        }
        // increase timeout
        \set_time_limit(60 * 5);

        $name = sprintf($this->getInjection('language')->translate('createThumbnailsNotification', 'labels', 'Attachment'), $entity->get('name'));

        try {
            $this->getInjection('thumbnail')->createThumbnail($entity, 'small');
            $this->getInjection('queueManager')->push($name, 'QueueManagerCreateThumbnails', ['id' => $entity->get('id')]);
        } catch (\Throwable $e) {
            // ignore all errors
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
        $this->addDependency('thumbnail');
    }
}

