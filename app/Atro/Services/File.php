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

class File extends Base
{
    protected $mandatorySelectAttributeList = ['storageId', 'path', 'thumbnailsPath', 'mimeType', 'typeId', 'typeName'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $fileNameParts = explode('.', $entity->get('name'));

        $entity->set('extension', strtolower(array_pop($fileNameParts)));
        $entity->set('downloadUrl', $entity->getDownloadUrl());
        $entity->set('smallThumbnailUrl', $entity->getSmallThumbnailUrl());
        $entity->set('mediumThumbnailUrl', $entity->getMediumThumbnailUrl());
        $entity->set('largeThumbnailUrl', $entity->getLargeThumbnailUrl());
        $entity->set('hasOpen', in_array($entity->get('extension'), $this->getMetadata()->get('app.file.image.hasPreviewExtensions', [])));
    }

    /**
     * @param \stdClass $attachment
     *
     * @return array
     */
    public function createEntity($attachment)
    {
        // set default storage on create
        if (!property_exists($attachment, 'storageId')) {
            $default = $this->getServiceFactory()->create('Folder')->getDefaultStorage('');
            if (!empty($default['id'])) {
                $attachment->storageId = $default['id'];
            }
        }

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

    public function createFileViaContents(\stdClass $attachment, string $contents): array
    {
        $attachment->fileContents = "data:application/unknown;base64," . base64_encode($contents);

        return $this->createEntity($attachment);
    }

    protected function createFileEntity(\stdClass $attachment): array
    {
        $entity = parent::createEntity($attachment);
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
