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

namespace Espo\Services;

use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\Core\FilePathBuilder;
use Imagick;

class Attachment extends Record
{
    protected const CHUNKS_DIR = 'data/chunks/';

    protected $notFilteringAttributeList = ['contents'];

    protected $attachmentFieldTypeList = ['file', 'image', 'attachmentMultiple', 'asset'];

    protected $inlineAttachmentFieldTypeList = ['text', 'wysiwyg', 'wysiwygMultiLang'];

    public function deleteOld(): bool
    {
        $days = $this->getConfig()->get('deletedAttachmentsMaxDays', 14);
        if ($days === 0) {
            return true;
        }
        $date = (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s');

        $repository = $this->getEntityManager()->getRepository('Attachment');
        $attachments = $repository
            ->where([
                'deleted'     => 1,
                'createdAt<=' => $date
            ])
            ->limit(0, 3000)
            ->find(["withDeleted" => true]);

        $fileManager = $this->getInjection('fileStorageManager');
        foreach ($attachments as $entity) {
            $fileManager->unlink($entity);
        }

        $connection = $this->getEntityManager()->getConnection();
        $connection->createQueryBuilder()
            ->delete('attachment')
            ->where('created_at < :date')
            ->andWhere('deleted = :deleted')
            ->setParameter('date', $date)
            ->setParameter('deleted', true, ParameterType::BOOLEAN)
            ->executeStatement();

        return true;
    }

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

        if ($this->attachmentHasAsset($attachment)) {
            $this->createAsset($entity, $attachment);
        }

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
     * @param Imagick $imagick
     *
     * @return string|null
     */
    public static function getColorSpace(Imagick $imagick): ?string
    {
        $colorId = $imagick->getImageColorspace();

        if (!$colorId) {
            return null;
        }

        foreach ((new \ReflectionClass($imagick))->getConstants() as $name => $value) {
            if (stripos($name, "COLORSPACE_") !== false && $value == $colorId) {
                $el = explode("_", $name);
                array_shift($el);

                return implode("_", $el);
            }
        }

        return null;
    }

    public function createEntityByUrl(string $url, bool $validateAttachment = true): Entity
    {
        // parse url
        $parsedUrl = parse_url($url);

        // prepare filename
        $filename = basename($parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path']);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
            if (!empty($query['filename'])) {
                $filename = $query['filename'];
            }
        }

        $attachment = new \stdClass();
        $attachment->name = $filename;
        $attachment->relatedType = 'Asset';
        $attachment->field = 'file';
        $attachment->storageFilePath = $this->getEntityManager()->getRepository('Attachment')->getDestPath(FilePathBuilder::UPLOAD);
        $attachment->storageThumbPath = $this->getEntityManager()->getRepository('Attachment')->getDestPath(FilePathBuilder::UPLOAD);

        $fullPath = $this->getConfig()->get('filesPath', 'upload/files/') . $attachment->storageFilePath;
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $attachment->fileName = $fullPath . '/' . $attachment->name;

        // load file from url
        set_time_limit(0);
        $fp = fopen($attachment->fileName, 'w+');
        if ($fp === false) {
            throw new Error(sprintf($this->getInjection('language')->translate('fileResourceWriteFailed', 'exceptions', 'Asset'), $attachment->name));
        }
        $ch = curl_init(str_replace(" ", "%20", $url));
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!in_array($responseCode, [200, 201])) {
            if (file_exists($attachment->fileName)) {
                unlink($attachment->fileName);
            }
            throw new Error(sprintf($this->getInjection('language')->translate('urlDownloadFailed', 'exceptions', 'Asset'), $url));
        }

        $attachment->type = mime_content_type($attachment->fileName);
        $entity = parent::createEntity($attachment);

        return $entity;
    }

    public function attachmentHasAsset(\stdClass $input = null): bool
    {
        if (!empty($input) && property_exists($input, 'relatedType') && $input->relatedType === 'Note') {
            return false;
        }

        if (property_exists($input, 'relatedType') && property_exists($input, 'field')) {
            if ($this->getMetadata()->get(['entityDefs', $input->relatedType, 'fields', $input->field, 'noAsset'])) {
                return false;
            }
        }

        return true;
    }

    public function createAsset(Entity $entity, ?\stdClass $attachment = null): void
    {
        if (empty($entity->getAsset())) {
            $type = null;
            if (!empty($attachment) && !empty($attachment->modelAttributes->attributeAssetType)) {
                $type = $attachment->modelAttributes->attributeAssetType;
            }
            $this->getRepository()->createAsset($entity, false, $type);
        }
    }

    /**
     * @param      $attachment
     * @param null $path
     *
     * @return array
     * @throws \ImagickException
     * @throws \ReflectionException
     */
    public function getImageInfo($attachment, $path = null): array
    {
        if (stripos($attachment->get("type"), "image/") === false) {
            return [];
        }

        $path = $path ?? $this->getPath($attachment);

        $image = new Imagick($path);

        if ($imageInfo = getimagesize($path)) {
            $result = [
                "width"       => $image->getImageWidth(),
                "height"      => $image->getImageHeight(),
                "color_space" => self::getColorSpace($image),
                "color_depth" => $image->getImageDepth(),
                'orientation' => $this->getPosition($image->getImageWidth(), $image->getImageHeight()),
                'mime'        => $image->getImageMimeType(),
            ];
        }

        return $result ?? [];
    }

    /**
     * @param      $attachment
     * @param null $path
     *
     * @return array
     */
    public function getFileInfo($attachment, $path = null): array
    {
        $path = $path ?? $this->getPath($attachment);

        if ($pathInfo = pathinfo($path)) {
            $result['extension'] = $pathInfo['extension'];
            $result['base_name'] = $pathInfo['basename'];
        }

        $result['size'] = filesize($path);

        return $result;
    }

    public function changeName(Entity $attachment, string $newName)
    {
        return $this->getRepository()->renameFile($attachment, $newName);
    }

    /**
     * @param \Espo\Entities\Attachment $attachment
     *
     * @return mixed
     */
    private function getPath(\Espo\Entities\Attachment $attachment)
    {
        return $this->getRepository()->getFilePath($attachment);
    }

    /**
     * @param $width
     * @param $height
     *
     * @return string
     */
    private function getPosition($width, $height): string
    {
        $result = "Square";

        if ($width > $height) {
            $result = "Landscape";
        } elseif ($width < $height) {
            $result = "Portrait";
        }

        return $result;
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
        $this->addDependency('fileStorageManager');
    }
}

