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

    protected const CHUNKS_DIR = 'data/chunks/';

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

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        parent::beforeCreateEntity($entity, $data);

        if (property_exists($data, 'file') && !empty($data->file)) {
            $entity->_inputContents = $this->parseInputFileContent($data->file);
        }
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

    /**
     * Remove dirs for old chunks
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
                    break;
                }
            }
        }
    }

    protected function parseInputFileContent(string $fileContent): string
    {
        $arr = explode(',', $fileContent);
        $contents = '';
        if (count($arr) > 1) {
            $contents = $arr[1];
        }

        return base64_decode($contents);
    }
}
