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

namespace Atro\Core\Thumbnail;

use Atro\Core\Container;
use Atro\Core\Utils\PDFLib;
use Atro\Core\Exceptions\Error;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\File\Manager;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Attachment;
use Espo\ORM\EntityManager;
use Gumlet\ImageResize;

class Image
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
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
        if ($this->isPdf($attachment)) {
            return $this->createImageFromPdf($attachment->getFilePath());
        }

        return $attachment->getFilePath();
    }

    protected function isPdf(Attachment $attachment): bool
    {
        $parts = explode('.', $attachment->get('name'));

        return strtolower(array_pop($parts)) === 'pdf';
    }

    protected function createImageFromPdf(string $pdfPath): string
    {
        $pathParts = explode('/', $pdfPath);
        $fileName = array_pop($pathParts);
        $dirPath = implode('/', $pathParts);

        $original = $dirPath . '/page-1.png';
        if (!file_exists($original)) {
            $pdflib = new PDFLib($this->getConfig());
            $pdflib->setPdfPath($pdfPath);
            $pdflib->setOutputPath($dirPath);
            $pdflib->setImageFormat(PDFLib::$IMAGE_FORMAT_PNG);
            $pdflib->setPageRange(1, 1);
            $pdflib->setFilePrefix('page-');
            $pdflib->convert();
        }

        return $original;
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getFileManager(): Manager
    {
        return $this->container->get('fileManager');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }
}