<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Services;

use Atro\Core\Templates\Services\Base;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class File extends Base
{
    protected $mandatorySelectAttributeList = ['storageId', 'path', 'thumbnailsPath'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $fileNameParts = explode('.', $entity->get('name'));

        $entity->set('extension', strtolower(array_pop($fileNameParts)));
        $entity->set('downloadUrl', $entity->getDownloadUrl());
        $entity->set('smallThumbnailUrl', $entity->getSmallThumbnailUrl());
        $entity->set('mediumThumbnailUrl', $entity->getMediumThumbnailUrl());
        $entity->set('largeThumbnailUrl', $entity->getLargeThumbnailUrl());
    }

    public function createEntity($attachment)
    {
        echo '<pre>';
        print_r('createEntity');
        die();

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

        if ($this->attachmentHasAsset($attachment)) {
            $this->validateAttachment($entity, $attachment);
            $this->createAsset($entity, $attachment);
        }

        return $entity;
    }


    public function createChunks(\stdClass $attachment): array
    {
        echo '<pre>';
        print_r('createChunks');
        die();

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
}
