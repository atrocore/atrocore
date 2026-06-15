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

namespace Atro\Core\Utils;

use Atro\Core\Container;
use Atro\Entities\File as FileEntity;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\File\Manager;
use Gumlet\ImageResize;
use Gumlet\ImageResizeException;

class Thumbnail
{
    protected Container $container;

    private ?string $largestSizeKey = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function preparePath(FileEntity $file, string $size): string
    {
        $thumbnailPath = trim($this->getConfig()->get('thumbnailsPath', 'upload/thumbnails'), DIRECTORY_SEPARATOR);
        if (!empty($file->get('thumbnailsPath'))) {
            $thumbnailPath .= DIRECTORY_SEPARATOR . trim($file->get('thumbnailsPath'));
        }

        if (!$this->isResizeSupported($file)) {
            return $thumbnailPath . DIRECTORY_SEPARATOR . $file->get('name');
        }

        $thumbnailPath .= DIRECTORY_SEPARATOR . trim($size);

        $name = explode('.', $file->get('name'));
        array_pop($name);
        $name = implode('.', $name) . '.png';

        $thumbnailPath .= DIRECTORY_SEPARATOR . $name;

        return $thumbnailPath;
    }

    public function hasThumbnail(FileEntity $file, string $size): bool
    {
        return file_exists('public' . DIRECTORY_SEPARATOR . $this->preparePath($file, $size));
    }

    public function getPath(FileEntity $file, string $size): ?string
    {
        $ext = strtolower(pathinfo($file->get('name'), PATHINFO_EXTENSION));
        if (!in_array($ext, $this->getMetadata()->get(['app', 'extensionsWithThumbnail'], []))) {
            return null;
        }

        return "thumbnail/{$size}/{$file->get('id')}.png";
    }

    public function getLargestSizeKey(): string
    {
        if ($this->largestSizeKey !== null) {
            return $this->largestSizeKey;
        }

        $largest  = '';
        $bestArea = 0;
        foreach ($this->getMetadata()->get('app.thumbnailTypes', []) as $key => $cfg) {
            [$w, $h] = $cfg['size'] ?? [0, 0];
            if ($w * $h > $bestArea) {
                $bestArea = $w * $h;
                $largest  = $key;
            }
        }

        return $this->largestSizeKey = ($largest ?: 'large');
    }

    public function createLargestThumbnail(FileEntity $file, string $localPath, bool $sync = false): void
    {
        if (!$this->isResizeSupported($file)) {
            return;
        }
        $sizeKey = $this->getLargestSizeKey();
        $this->create($localPath, $sizeKey, $this->preparePath($file, $sizeKey));
    }

    public function create(string $originFilePath, string $size, string $thumbnailPath): bool
    {
        $thumbnailPath = 'public' . DIRECTORY_SEPARATOR . $thumbnailPath;
        if (file_exists($thumbnailPath)) {
            return false;
        }

        $imageSizes = $this->getMetadata()->get("app.thumbnailTypes.$size.size");
        if (empty($imageSizes)) {
            return false;
        }

        $pdfTmpDir = null;
        if ($this->isPdf($originFilePath)) {
            [$originFilePath, $pdfTmpDir] = $this->createImageFromPdf($originFilePath);
        }

        try {
            $image = new ImageResize($originFilePath);
        } catch (ImageResizeException $e) {
            return false;
        } finally {
            if ($pdfTmpDir !== null) {
                Util::removeDir($pdfTmpDir);
            }
        }

        list($w, $h) = $imageSizes;

        $image->resizeToBestFit($w, $h);

        $thumbnailDirPath = $this->getFileManager()->getFileDir($thumbnailPath);
        if (!is_dir($thumbnailDirPath)) {
            $this->getFileManager()->mkdir($thumbnailDirPath, 0777, true);
        }

        return $this->getFileManager()->putContents($thumbnailPath, $image->getImageAsString());
    }

    public function deleteAllThumbnails(FileEntity $file): void
    {
        foreach (array_keys($this->getMetadata()->get(['app', 'thumbnailTypes'], [])) as $size) {
            $path = 'public' . DIRECTORY_SEPARATOR . $this->preparePath($file, $size);
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    protected function createImageFromPdf(string $pdfPath): array
    {
        $tmpDir = 'data' . DIRECTORY_SEPARATOR . '.pdf-thumbnail-tmp' . DIRECTORY_SEPARATOR . uniqid();
        $this->getFileManager()->mkdir($tmpDir, 0777, true);
        $pdflib = new PDFLib($this->getConfig());
        $pdflib->setPdfPath($pdfPath);
        $pdflib->setOutputPath($tmpDir);
        $pdflib->setImageFormat(PDFLib::$IMAGE_FORMAT_PNG);
        $pdflib->setPageRange(1, 1);
        $pdflib->setFilePrefix('page-');
        $pdflib->convert();

        return [$tmpDir . '/page-1.png', $tmpDir];
    }

    public function isSvg(FileEntity $file): bool
    {
        return $file->get('mimeType') === 'image/svg+xml';
    }

    public function isResizeSupported(FileEntity $file): bool
    {
        if ($file->get('mimeType') === 'image/avif') {
            return gd_info()['AVIF Support'] ?? false;
        }

        if ($this->isSvg($file)) {
            return false;
        }

        return true;
    }

    public function isPdf(string $fileName): bool
    {
        $parts = explode('.', $fileName);

        return strtolower(array_pop($parts)) === 'pdf';
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
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