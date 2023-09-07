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

namespace Atro\Core\Thumbnail;

use Espo\Core\Exceptions\Error;
use Espo\Core\Injectable;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Attachment;
use Espo\ORM\EntityManager;
use Gumlet\ImageResize;
use Espo\Core\Utils\File\Manager;

/**
 * Class Image
 */
class Image extends Injectable
{
    public function __construct()
    {
        $this->addDependency('metadata');
        $this->addDependency('config');
        $this->addDependency('fileManager');
        $this->addDependency('entityManager');
    }

    public function createThumbnailByPath(string $path): ?Attachment
    {
        $thumbsPath = $this->getConfig()->get('thumbnailsPath', 'upload/thumbnails/');
        if (strpos($path, $thumbsPath) === false) {
            return null;
        }

        $pathParts = explode('/', $path);

        $fileName = array_pop($pathParts);
        $size = array_pop($pathParts);
        $storageThumbPath = str_replace([$thumbsPath, '/' . $size, '/' . $fileName], ['', '', ''], $path);

        $attachmentRepository = $this->getEntityManager()->getRepository("Attachment");

        $attachment = $attachmentRepository->where(['storageThumbPath' => $storageThumbPath])->findOne();
        if (empty($attachment)) {
            $attachment = $attachmentRepository->where(['storageFilePath' => $storageThumbPath])->findOne();
            if (empty($attachment)) {
                return null;
            }
        }

        if ($this->createThumbnail($attachment, $size)) {
            return $attachment;
        }

        return null;
    }

    public function createThumbnail(Attachment $attachment, string $size): bool
    {
        if (empty($attachment->getThumbPath($size)) || file_exists($attachment->getThumbPath($size)) || empty($attachment->getFilePath())) {
            return false;
        }

        try {
            $image = new ImageResize($this->getImageFilePath($attachment));
        } catch (\Gumlet\ImageResizeException $e) {
            return false;
        }

        $imageSizes = $this->getMetadata()->get(['app', 'imageSizes'], []);

        if (!$imageSizes[$size]) {
            throw new Error('Wrong file size');
        }

        list($w, $h) = $imageSizes[$size];

        $image->resizeToBestFit($w, $h);

        return $this->getFileManager()->putContents($attachment->getThumbPath($size), $image->getImageAsString());
    }

    protected function getImageFilePath(Attachment $attachment): string
    {
        return $attachment->getFilePath();
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }

    protected function getFileManager(): Manager
    {
        return $this->getInjection('fileManager');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getInjection('entityManager');
    }

    protected function getConfig(): Config
    {
        return $this->getInjection('config');
    }
}