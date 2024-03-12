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
use Atro\Entities\File as FileEntity;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\File\Manager;
use Espo\Core\Utils\Metadata;
use Gumlet\ImageResize;
use Gumlet\ImageResizeException;

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
            if (!$this->create($this->getImageFilePath($file), $size, $thumbnailPath)) {
                return null;
            }
        }

        return $thumbnailPath;
    }

    public function create(string $originFilePath, string $size, string $thumbnailPath): bool
    {
        if (file_exists($thumbnailPath)) {
            return false;
        }

        $imageSizes = $this->getMetadata()->get(['app', 'imageSizes'], []);
        if (!$imageSizes[$size]) {
            return false;
        }

        try {
            $image = new ImageResize($originFilePath);
        } catch (ImageResizeException $e) {
            return false;
        }

        list($w, $h) = $imageSizes[$size];

        $image->resizeToBestFit($w, $h);

        $thumbnailDirPath = $this->getFileManager()->getFileDir($thumbnailPath);
        if (!is_dir($thumbnailDirPath)) {
            $this->getFileManager()->mkdir($thumbnailDirPath, 0777, true);
        }

        return $this->getFileManager()->putContents($thumbnailPath, $image->getImageAsString());
    }

    protected function getImageFilePath(FileEntity $file): string
    {
        if ($this->isPdf($file)) {
            return $this->createImageFromPdf($file->getFilePath());
        }

        return $file->getFilePath();
    }

    protected function isPdf(FileEntity $file): bool
    {
        $parts = explode('.', $file->get('name'));

        return strtolower(array_pop($parts)) === 'pdf';
    }

    protected function createImageFromPdf(string $pdfPath): string
    {
        $dirPath = explode(DIRECTORY_SEPARATOR, $pdfPath);
        array_pop($dirPath);
        $dirPath = implode(DIRECTORY_SEPARATOR, $dirPath);

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

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }
}