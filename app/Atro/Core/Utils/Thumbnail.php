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

namespace Atro\Core\Utils;

use Atro\Core\Container;
use Atro\Core\Utils\PDFLib;
use Atro\Core\Exceptions\Error;
use Atro\Entities\File as FileEntity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\File\Manager;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Attachment;
use Espo\ORM\EntityManager;
use Gumlet\ImageResize;

class Thumbnail
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getPath(FileEntity $file, string $size): ?string
    {
        if (!in_array($file->get('mimeType'), $this->getMetadata()->get(['app', 'typesWithThumbnails'], []))) {
            return null;
        }

        $thumbnailPath = trim($this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'), DIRECTORY_SEPARATOR);
        if (!empty(trim($file->get('thumbnailsPath'), DIRECTORY_SEPARATOR))) {
            $thumbnailPath .= DIRECTORY_SEPARATOR . trim($file->get('thumbnailsPath'));
        }
        $thumbnailPath .= DIRECTORY_SEPARATOR . trim($size);

        $name = explode('.', $file->get('name'));
        array_pop($name);
        $name = implode('.', $name) . '.png';

        $thumbnailPath .= DIRECTORY_SEPARATOR . $name;

        if (!file_exists($thumbnailPath)) {
            // create thumbnail if not exist
            if (!$this->create($file, $size)) {
                return null;
            }
        }

        return $thumbnailPath;
    }

//    //
//
//    public function createThumbnail(string $originalPath, string $type, string $thumbnailPath): bool
//    {
//        try {
//            $image = new ImageResize($originalPath);
//        } catch (\Throwable $e) {
//            return false;
//        }
//
//        $thumbnailDirPath = explode(DIRECTORY_SEPARATOR, $thumbnailPath);
//        array_pop($thumbnailDirPath);
//        $thumbnailDirPath = implode(DIRECTORY_SEPARATOR, $thumbnailDirPath);
//
//        list($w, $h) = $this->getMetadata()->get(['app', 'imageSizes'], [])[$type];
//        $image->resizeToBestFit($w, $h);
//        if (!is_dir($thumbnailDirPath)) {
//            $this->getFileManager()->mkdir($thumbnailDirPath, 0777, true);
//        }
//        $this->getFileManager()->putContents($thumbnailPath, $image->getImageAsString());
//
//        return true;
//    }

    public function create(FileEntity $file, string $size): bool
    {
        $origin = $this->getImageFilePath($file);
        $thumbnailPath = $this->getPath($file, $size);

        echo '<pre>';
        print_r('123');
        die();

        if (file_exists($thumbnailPath) || empty($origin)) {
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

    protected function getImageFilePath(FileEntity $file): string
    {
        if ($this->isPdf($file)) {
            return $this->createImageFromPdf($attachment->getFilePath());
        }

        return $attachment->getFilePath();
    }

    protected function isPdf(FileEntity $file): bool
    {
        $parts = explode('.', $file->get('name'));

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