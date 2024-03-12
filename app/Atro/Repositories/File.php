<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\FileStorage\LocalFileStorageInterface;
use Atro\Entities\File as FileEntity;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\FilePathBuilder;
use Espo\ORM\Entity;
use Atro\Core\Utils\Thumbnail;

class File extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('storageId')) {
                throw new BadRequest($this->getInjection('language')->translate('fileStorageCannotBeChanged', 'exceptions', 'File'));
            }

            if ($entity->isAttributeChanged('name')) {
                if ($this->isExtensionChanged($entity)) {
                    throw new BadRequest($this->getInjection('language')->translate('fileExtensionCannotBeChanged', 'exceptions', 'File'));
                }

                $fileNameRegexPattern = $this->getConfig()->get('fileNameRegexPattern');
                if (!empty($fileNameRegexPattern)) {
                    $nameWithoutExt = explode('.', (string)$entity->get('name'));
                    array_pop($nameWithoutExt);
                    $nameWithoutExt = implode('.', $nameWithoutExt);

                    if (!preg_match($fileNameRegexPattern, $nameWithoutExt)) {
                        throw new BadRequest(sprintf($this->getInjection('language')->translate('fileNameNotValidByUserRegex', 'exceptions', 'File'), $fileNameRegexPattern));
                    }
                }

                if (!$this->getStorage($entity)->rename($entity)) {
                    throw new BadRequest($this->getInjection('language')->translate('fileRenameFailed', 'exceptions', 'File'));
                }
            }
        }

        if (empty($entity->get('thumbnailsPath'))) {
            if (!empty($entity->get('path'))) {
                $entity->set('thumbnailsPath', $entity->get('path'));
            } else {
                $thumbnailsDirPath = trim($this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'), '/');
                $entity->set('thumbnailsPath', $this->getPathBuilder()->createPath($thumbnailsDirPath . '/'));
            }
        }
    }

    public function isExtensionChanged(FileEntity $file): bool
    {
        $fetchedParts = explode('.', (string)$file->getFetched('name'));
        $fetchedExt = array_pop($fetchedParts);

        $parts = explode('.', (string)$file->get('name'));
        $ext = array_pop($parts);

        return $fetchedExt !== $ext;
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        // delete origin file
        if (!$this->getStorage($entity)->delete($entity)) {
            throw new BadRequest($this->getInjection('language')->translate('fileDeleteFailed', 'exceptions', 'File'));
        }

        // delete thumbnails
        foreach (['small', 'medium', 'large'] as $size) {
            $thumbnailPath = $this->getThumbnail()->getPath($entity, $size);
            if (!empty($thumbnailPath) && file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
        }
    }

    public function getContents(FileEntity $file): string
    {
        return $this->getStorage($file)->getContents($file);
    }

    public function getFilePath(FileEntity $file): string
    {
        $fileStorage = $this->getStorage($file);

        if ($fileStorage instanceof LocalFileStorageInterface) {
            return $fileStorage->getLocalPath($file);
        }

        return $fileStorage->getUrl($file);
    }

    public function getDownloadUrl(FileEntity $file): string
    {
        return $this->getStorage($file)->getUrl($file);
    }

    public function getSmallThumbnailUrl(FileEntity $file): ?string
    {
        return $this->getThumbnail()->getPath($file, 'small');
    }

    public function getMediumThumbnailUrl(FileEntity $file): ?string
    {
        return $this->getThumbnail()->getPath($file, 'medium');
    }

    public function getLargeThumbnailUrl(FileEntity $file): ?string
    {
        return $this->getThumbnail()->getPath($file, 'large');
    }

    public function getStorage(FileEntity $file): FileStorageInterface
    {
        return $this->getInjection('container')->get($file->get('storage')->get('type') . 'Storage');
    }

    protected function getPathBuilder(): FilePathBuilder
    {
        return $this->getInjection('container')->get('filePathBuilder');
    }

    protected function getThumbnail(): Thumbnail
    {
        return $this->getInjection('container')->get(Thumbnail::class);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('language');
    }
}
