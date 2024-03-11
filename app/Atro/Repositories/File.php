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

use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Entities\File as FileEntity;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\FilePathBuilder;
use Espo\ORM\Entity;
use Gumlet\ImageResize;

class File extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (empty($entity->get('thumbnailsPath'))) {
            if (!empty($entity->get('path'))) {
                $entity->set('thumbnailsPath', $entity->get('path'));
            } else {
                $thumbnailsDirPath = trim($this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'), '/');
                $entity->set('thumbnailsPath', $this->getPathBuilder()->createPath($thumbnailsDirPath . '/'));
            }
        }
    }

    public function getDownloadUrl(FileEntity $file): string
    {
        return $this->getStorage($file)->getUrl($file);
    }

    public function getSmallThumbnailUrl(FileEntity $file): ?string
    {
        return $this->getThumbnailUrl($file, 'small');
    }

    public function getMediumThumbnailUrl(FileEntity $file): ?string
    {
        return $this->getThumbnailUrl($file, 'medium');
    }

    public function getLargeThumbnailUrl(FileEntity $file): ?string
    {
        return $this->getThumbnailUrl($file, 'large');
    }

    public function getThumbnailUrl(FileEntity $file, string $type): ?string
    {
        if (!in_array($file->get('mimeType'), $this->getMetadata()->get(['app', 'typesWithThumbnails'], []))) {
            return null;
        }

        $thumbnailDirPath = trim($this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'), DIRECTORY_SEPARATOR);
        if (!empty(trim($file->get('thumbnailsPath'), DIRECTORY_SEPARATOR))) {
            $thumbnailDirPath .= DIRECTORY_SEPARATOR . trim($file->get('thumbnailsPath'));
        }
        $thumbnailDirPath .= DIRECTORY_SEPARATOR . trim($type);

        $name = explode('.', $file->get("name"));
        array_pop($name);
        $name = implode('.', $name) . '.png';

        $thumbnailPath = $thumbnailDirPath . DIRECTORY_SEPARATOR . $name;

        // create thumbnail if not exist
        if (!file_exists($thumbnailPath)) {
            $original = $this->getStorage($file)->getLocalPath($file);
            try {
                $image = new ImageResize($original);
            } catch (\Throwable $e) {
                return null;
            }
            list($w, $h) = $this->getMetadata()->get(['app', 'imageSizes'], [])[$type];
            $image->resizeToBestFit($w, $h);
            if (!is_dir($thumbnailDirPath)) {
                mkdir($thumbnailDirPath, 0777, true);
            }
            file_put_contents($thumbnailPath, $image->getImageAsString());
        }

        return $thumbnailPath;
    }

    public function getStorage(FileEntity $file): FileStorageInterface
    {
        return $this->getInjection('container')->get($file->get('storage')->get('type') . 'Storage');
    }

    protected function getPathBuilder(): FilePathBuilder
    {
        return $this->getInjection('filePathBuilder');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('filePathBuilder');
    }
}
