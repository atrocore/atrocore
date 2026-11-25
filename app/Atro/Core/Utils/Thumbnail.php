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
use Espo\ORM\EntityManager;
use Gumlet\ImageResize;
use Gumlet\ImageResizeException;

/**
 * Thumbnails are stored outside the public directory and generated on request only, of the requested size only.
 * They are served by the Thumbnail entry point after the access to the file is checked.
 */
class Thumbnail
{
    public const string DIR = 'data/thumbnails';

    private const string LOCKS_DIR = self::DIR . '/.locks';
    private const string TMP_DIR = 'data/.thumbnail-tmp';

    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * The URL of the thumbnail relative to the site URL. The "cache" parameter changes together with the file
     * and the size of the thumbnail type, so the browser may keep the thumbnail as long as the URL is the same.
     */
    public function getUrl(FileEntity $file, string $size): ?string
    {
        if (!$this->isSizeValid($size) || !$this->isSupported($file)) {
            return null;
        }

        return "thumbnail/{$size}/{$file->get('id')}.png?cache=" . $this->getVersion($file, $size);
    }

    public function getVersion(FileEntity $file, string $size): string
    {
        return substr(md5(implode('|', [
            (string)$file->get('hash'),
            (string)$file->get('fileMtime'),
            json_encode($this->getMetadata()->get(['app', 'thumbnailTypes', $size, 'size'])),
        ])), 0, 12);
    }

    public function isSizeValid(string $size): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $size) === 1
            && !empty($this->getMetadata()->get(['app', 'thumbnailTypes', $size, 'size']));
    }

    public function isSupported(FileEntity $file): bool
    {
        $ext = strtolower(pathinfo((string)$file->get('name'), PATHINFO_EXTENSION));

        return in_array($ext, $this->getMetadata()->get(['app', 'extensionsWithThumbnail'], []));
    }

    public function getFilePath(FileEntity $file, string $size): string
    {
        $id = (string)$file->get('id');

        // the tail of an id is its random part, so the files are spread over the directories evenly
        return self::DIR . '/' . $size . '/' . substr($id, -2) . '/' . $id;
    }

    public function hasThumbnail(FileEntity $file, string $size): bool
    {
        return file_exists($this->getFilePath($file, $size));
    }

    /**
     * Returns the path to the thumbnail, creating it when it does not exist yet, or null when it cannot be provided now
     */
    public function generate(FileEntity $file, string $size): ?string
    {
        if (!$this->isSizeValid($size) || !$this->isSupported($file)) {
            return null;
        }

        $path = $this->getFilePath($file, $size);
        if (file_exists($path)) {
            return $path;
        }

        // concurrent requests of the same thumbnail wait for the one that creates it
        Util::createDir(self::LOCKS_DIR);
        $lock = fopen(self::LOCKS_DIR . '/' . $file->get('id') . '-' . $size, 'c');
        if ($lock === false) {
            return null;
        }

        try {
            flock($lock, LOCK_EX);
            if (!file_exists($path)) {
                $this->createThumbnail($file, $size, $path);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return file_exists($path) ? $path : null;
    }

    /**
     * Whether the thumbnail is being created in the background, so it is not available yet but will be
     */
    public function isPending(FileEntity $file, string $size): bool
    {
        return false;
    }

    /**
     * Resizes the origin image to the given thumbnail type and writes the result to the target path
     */
    public function create(string $originFilePath, string $size, string $targetPath): bool
    {
        $imageSizes = $this->getMetadata()->get(['app', 'thumbnailTypes', $size, 'size']);
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

        [$w, $h] = $imageSizes;
        $image->resizeToBestFit($w, $h);

        return $this->write($targetPath, $image->getImageAsString());
    }

    public function deleteAllThumbnails(FileEntity $file): void
    {
        foreach (array_keys($this->getMetadata()->get(['app', 'thumbnailTypes'], [])) as $size) {
            $path = $this->getFilePath($file, (string)$size);
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function deleteThumbnailsOfSize(string $size): void
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $size) === 1) {
            Util::removeDir(self::DIR . '/' . $size);
        }
    }

    public function getMimeType(string $path): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
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

    protected function createThumbnail(FileEntity $file, string $size, string $path): void
    {
        // a larger thumbnail is enough to create a smaller one, the original is not needed then
        if ($this->isResizeSupported($file) && !empty($larger = $this->findLargerThumbnail($file, $size))) {
            $this->create($larger, $size, $path);
            return;
        }

        $tmpDir = self::TMP_DIR . '/' . IdGenerator::unsortableId();
        Util::createDir($tmpDir);
        try {
            $localPath = $file->findOrCreateLocalFilePath($tmpDir);
            if ($this->isResizeSupported($file)) {
                $this->create($localPath, $size, $path);
            } else {
                $this->write($path, (string)file_get_contents($localPath));
            }
        } finally {
            Util::removeDir($tmpDir);
        }
    }

    protected function findLargerThumbnail(FileEntity $file, string $size): ?string
    {
        $area = $this->getArea($size);

        $found = null;
        $foundArea = null;
        foreach (array_keys($this->getMetadata()->get(['app', 'thumbnailTypes'], [])) as $candidate) {
            $candidateArea = $this->getArea((string)$candidate);
            if ($candidateArea <= $area || ($foundArea !== null && $candidateArea >= $foundArea)) {
                continue;
            }

            $path = $this->getFilePath($file, (string)$candidate);
            if (file_exists($path)) {
                $found = $path;
                $foundArea = $candidateArea;
            }
        }

        return $found;
    }

    protected function getArea(string $size): int
    {
        [$w, $h] = $this->getMetadata()->get(['app', 'thumbnailTypes', $size, 'size']) ?? [0, 0];

        return (int)$w * (int)$h;
    }

    /**
     * Writes atomically, so a thumbnail is never read while it is being written
     */
    protected function write(string $path, string $contents): bool
    {
        Util::createDir(dirname($path));

        $tmpPath = $path . '.' . IdGenerator::unsortableId() . '.tmp';
        if (file_put_contents($tmpPath, $contents) === false) {
            return false;
        }

        return rename($tmpPath, $path);
    }

    protected function createImageFromPdf(string $pdfPath): array
    {
        $tmpDir = 'data' . DIRECTORY_SEPARATOR . '.pdf-thumbnail-tmp' . DIRECTORY_SEPARATOR . uniqid();
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }
        $pdflib = new PDFLib($this->getConfig());
        $pdflib->setPdfPath($pdfPath);
        $pdflib->setOutputPath($tmpDir);
        $pdflib->setImageFormat(PDFLib::$IMAGE_FORMAT_PNG);
        $pdflib->setPageRange(1, 1);
        $pdflib->setFilePrefix('page-');
        $pdflib->convert();

        return [$tmpDir . '/page-1.png', $tmpDir];
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }
}
