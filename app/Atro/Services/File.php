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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class File extends Base
{
    protected $mandatorySelectAttributeList = ['storageId', 'path', 'thumbnailsPath', 'mimeType', 'typeId', 'typeName', 'data'];

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        foreach ($collection as $entity) {
            $entity->_pathPrepared = true;
            if (!empty($entity->get('folderId'))) {
                $entity->set('folderPath', $this->getEntityManager()->getRepository('Folder')->getFolderHierarchyData($entity->get('folderId')));
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $fileNameParts = explode('.', $entity->get('name'));
        $entity->set('extension', strtolower(end($fileNameParts)));
        $entity->set('downloadUrl', $entity->getDownloadUrl());
        if (in_array($entity->get('extension'), $this->getMetadata()->get('app.file.image.hasPreviewExtensions', []))) {
            $entity->set('hasOpen', true);
            if (!empty($entity->getSmallThumbnailUrl())) {
                $entity->set('smallThumbnailUrl', $this->getConfig()->getSiteUrl() . DIRECTORY_SEPARATOR . $entity->getSmallThumbnailUrl());
            }
            if (!empty($entity->getMediumThumbnailUrl())) {
                $entity->set('mediumThumbnailUrl', $this->getConfig()->getSiteUrl() . DIRECTORY_SEPARATOR . $entity->getMediumThumbnailUrl());
            }
            if (!empty($entity->getLargeThumbnailUrl())) {
                $entity->set('largeThumbnailUrl', $this->getConfig()->getSiteUrl() . DIRECTORY_SEPARATOR . $entity->getLargeThumbnailUrl());
            }
        }

        if (empty($entity->_pathPrepared)) {
            $folderPath = [];
            if (!empty($current = $entity->get('folder'))) {
                while (!empty($parent = $current->getParent())) {
                    $folderPath[] = [
                        'id'         => $current->get('id'),
                        'name'       => $current->get('name'),
                        'parentId'   => $parent->get('id'),
                        'parentName' => $parent->get('name'),
                    ];
                    $current = $parent;
                }
            }
            $entity->set('folderPath', $folderPath);
        }
    }

    public function createEntity($attachment)
    {
        if (property_exists($attachment, 'url')) {
            $url = (string)$attachment->url;
            unset($attachment->url);
            return $this->createFileViaUrl($attachment, $url);
        }

        $attachment->storageId = $this->getEntityManager()->getRepository('Folder')->getFolderStorage($attachment->folderId ?? '')->get('id');

        // for single upload
        if (!property_exists($attachment, 'piecesCount')) {
            if (empty($attachment->id)) {
                $attachment->id = Util::generateId();
            }

            return $this->createFileEntity($attachment);
        }

        if (empty($attachment->id)) {
            throw new BadRequest("ID is required if create via chunks.");
        }

        // create entity for validation
        $entity = $this->getRepository()->get();
        $entity->set($attachment);

        // validate required fields
        $this->checkRequiredFields($entity, $attachment);

        // validate fields by patterns
        $this->checkFieldsWithPattern($entity);

        $storageEntity = $this->getEntityManager()->getRepository('Storage')->get($entity->get('storageId'));
        if (empty($storageEntity)) {
            throw new BadRequest(
                sprintf($this->getInjection('language')->translate('fieldIsRequired', 'exceptions'), $this->getInjection('language')->translate('storage', 'fields', 'File'))
            );
        }

        $this->getRepository()->validateItemName($entity);

        /** @var FileStorageInterface $storage */
        $storage = $this->getInjection('container')->get($storageEntity->get('type') . 'Storage');

        $chunks = $storage->createChunk($attachment, $storageEntity);

        $result = [];
        if (count($chunks) === $attachment->piecesCount) {
            $attachment->allChunks = $chunks;
            try {
                $result = $this->createFileEntity($attachment);
            } catch (NotUnique $e) {
                $result['created'] = true;
            } catch (\Throwable $e) {
                // try to delete file
                if (!empty($fileEntity = $this->getMemoryStorage()->get("file_{$attachment->id}"))) {
                    $storage->delete($fileEntity);
                }
                throw $e;
            }
        }

        return array_merge($result, ['chunks' => $chunks]);
    }

    public function createFileViaContents(\stdClass $attachment, string $contents)
    {
        $attachment->fileContents = "data:application/unknown;base64," . base64_encode($contents);

        return $this->createEntity($attachment);
    }

    public function createFileViaUrl(\stdClass $attachment, string $url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new BadRequest("Invalid URL");
        }

        if (!property_exists($attachment, 'name')) {
            $attachment->name = basename($url);
            $extension = pathinfo($attachment->name, PATHINFO_EXTENSION);
            if (empty($extension)) {
                throw new BadRequest("The filename does not have an extension");
            }
        }

        $attachment->remoteUrl = str_replace(" ", "%20", $url);

        return $this->createEntity($attachment);
    }

    public function moveLocalFileToFileEntity(\stdClass $attachment, string $fileName)
    {
        $attachment->localFileName = $fileName;

        return $this->createEntity($attachment);
    }

    protected function createFileEntity(\stdClass $attachment)
    {
        if (property_exists($attachment, 'reupload') && !empty($attachment->reupload)) {
            $attachment->id = $attachment->reupload;
            $entity = parent::updateEntity($attachment->id, $attachment);
        } else {
            $entity = parent::createEntity($attachment);
        }

        if (!empty($this->getMemoryStorage()->get('importJobId'))) {
            return $entity;
        }

        $result = $entity->toArray();

        if (!empty($entity->get('hash'))) {
            $duplicate = $this->getRepository()->where(['hash' => $entity->get('hash'), 'id!=' => $entity->get('id')])->findOne();
            if (!empty($duplicate)) {
                $result['duplicate'] = $duplicate->toArray();
            }
        }

        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
