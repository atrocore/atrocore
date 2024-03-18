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

namespace Atro\Repositories;

use Atro\Core\AssetValidator;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\FileStorage\LocalFileStorageInterface;
use Atro\Core\FileValidator;
use Atro\Entities\File as FileEntity;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\FilePathBuilder;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Atro\Core\Utils\Thumbnail;

class File extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $this->prepareThumbnailsPath($entity);

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('storageId')) {
                throw new BadRequest($this->getInjection('language')->translate('fileStorageCannotBeChanged', 'exceptions', 'File'));
            }

            if ($entity->isAttributeChanged('name')) {
                if ($this->isExtensionChanged($entity)) {
                    throw new BadRequest($this->getInjection('language')->translate('fileExtensionCannotBeChanged', 'exceptions', 'File'));
                }

                if (!$this->isNameValid($entity)) {
                    throw new BadRequest(
                        sprintf($this->getInjection('language')->translate('fileNameNotValidByUserRegex', 'exceptions', 'File'), $this->getConfig()->get('fileNameRegexPattern'))
                    );
                }

                if (!$this->getStorage($entity)->rename($entity)) {
                    throw new BadRequest($this->getInjection('language')->translate('fileRenameFailed', 'exceptions', 'File'));
                }
            }
        } else {
            $fileExtension = pathinfo($entity->get('name'), PATHINFO_EXTENSION);
            if (empty($options['scanning']) && !in_array(strtolower($fileExtension), $this->getConfig()->get('whitelistedExtensions'))) {
                throw new BadRequest(sprintf($this->getInjection('language')->translate('invalidFileExtension', 'exceptions', 'File'), $fileExtension));
            }

            // create origin file
            if (empty($options['scanning']) && !$this->getStorage($entity)->create($entity)) {
                throw new BadRequest($this->getInjection('language')->translate('fileCreateFailed', 'exceptions', 'File'));
            }
        }

        // validate via type
        if ($entity->isAttributeChanged('typeId') && !empty($entity->get('typeId'))) {
            $this->getInjection('container')->get(FileValidator::class)->validateFile($entity, true);
        }
    }

    public function prepareThumbnailsPath(FileEntity $file): void
    {
        if (empty($file->get('thumbnailsPath'))) {
            if (!empty($file->get('path'))) {
                $file->set('thumbnailsPath', $file->get('path'));
            } else {
                $thumbnailsDirPath = trim($this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'), '/');
                $file->set('thumbnailsPath', $this->getPathBuilder()->createPath($thumbnailsDirPath . DIRECTORY_SEPARATOR));
            }
        }
    }

    public function isNameValid(FileEntity $file): bool
    {
        $fileNameRegexPattern = $this->getConfig()->get('fileNameRegexPattern');
        if (!empty($fileNameRegexPattern)) {
            $nameWithoutExt = explode('.', (string)$file->get('name'));
            array_pop($nameWithoutExt);
            $nameWithoutExt = implode('.', $nameWithoutExt);
            return preg_match($fileNameRegexPattern, $nameWithoutExt);
        }

        return true;
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
        $this->addDependency('fileValidator');
    }
}
