<?php

namespace Treo\EntryPoints;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Treo\Core\EntryPoints\AbstractEntryPoint;
use Treo\Core\FileStorage\Storages\UploadDir;
use Treo\Entities\Attachment;

/**
 * Class Image
 *
 * @package Treo\EntryPoints
 */
class Image extends AbstractEntryPoint
{
    /**
     * @var array
     */
    protected $allowedFileTypes = ['image/jpeg', 'image/png', 'image/gif'];

    /**
     * @throws BadRequest
     * @throws Error
     * @throws Forbidden
     * @throws NotFound
     */
    public function run()
    {
        if (empty($_GET['id'])) {
            throw new BadRequest();
        }

        // prepare size
        $size = !empty($_GET['size']) ? $_GET['size'] : null;

        $this->show($_GET['id'], $size);
    }

    /**
     * @param mixed $id
     * @param mixed $size
     * @param bool  $disableAccessCheck
     *
     * @throws Error
     * @throws Forbidden
     * @throws NotFound
     */
    protected function show($id, $size, $disableAccessCheck = false)
    {
        $attachment = $this->getEntityManager()->getEntity('Attachment', $id);

        if (!$attachment) {
            throw new NotFound();
        }

        if (!$disableAccessCheck && !$this->checkAttachment($attachment)) {
            throw new Forbidden();
        }
        $filePath = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);

        $fileType = $attachment->get('type');

        if (!file_exists($filePath) && !file_exists($attachment->get("tmpPath"))) {
            throw new NotFound();
        }

        if (!in_array($fileType, $this->allowedFileTypes)) {
            throw new Error();
        }

        $content = $this->getFileContent($attachment, $filePath, $fileType, $size);

        if (!empty($size)) {
            $fileName = $size . '-' . $attachment->get('name');
        } else {
            $fileName = $attachment->get('name');
        }
        header('Content-Disposition:inline;filename="' . $fileName . '"');
        if (!empty($fileType)) {
            header('Content-Type: ' . $fileType);
        }
        header('Pragma: public');
        header('Cache-Control: max-age=360000, must-revalidate');
        $fileSize = mb_strlen($content, "8bit");
        if ($fileSize) {
            header('Content-Length: ' . $fileSize);
        }
        echo $content;
        exit;
    }

    /**
     * @param $attachment
     * @param $filePath
     * @param $fileType
     * @param $size
     *
     * @return false|string
     * @throws Error
     */
    protected function getFileContent($attachment, $filePath, $fileType, $size)
    {
        $filePath = $attachment->get('tmpPath') ?? $filePath;

        if (empty($size)) {
            return file_get_contents($filePath);
        }

        if (empty($this->getImageSize($size))) {
            throw new Error();
        }

        $thumbFilePath = $this->getThumbPath($attachment, $size);

        if (!file_exists($thumbFilePath)) {
            $contents = $this->getThumbImage($filePath, $fileType, $size);

            if (!$this->isTmp($attachment)) {
                $this->getContainer()->get('fileManager')->putContents($thumbFilePath, $contents);
            }
        } else {
            $contents = file_get_contents($thumbFilePath);
        }

        return $contents;
    }

    /**
     * @param $a
     * @param $size
     *
     * @return string
     */
    protected function getThumbPath($a, $size)
    {
        return UploadDir::BASE_THUMB_PATH . $a->get('storageFilePath') . "/{$size}/" . $a->get('name');
    }

    /**
     * @param $attachment
     *
     * @return bool
     */
    protected function isTmp($attachment): bool
    {
        return $attachment->get('tmpPath') ? true : false;
    }

    /**
     * @param $filePath
     * @param $fileType
     * @param $size
     *
     * @return false|string
     * @throws Error
     */
    protected function getThumbImage($filePath, $fileType, $size)
    {
        if (!@is_array(getimagesize($filePath))) {
            throw new Error();
        }

        list($originalWidth, $originalHeight) = getimagesize($filePath);
        list($width, $height) = $this->getImageSize($size);

        if ($originalWidth <= $width && $originalHeight <= $height) {
            $targetWidth = $originalWidth;
            $targetHeight = $originalHeight;
        } else {
            if ($originalWidth > $originalHeight) {
                $targetWidth = $width;
                $targetHeight = $originalHeight / ($originalWidth / $width);
                if ($targetHeight > $height) {
                    $targetHeight = $height;
                    $targetWidth = $originalWidth / ($originalHeight / $height);
                }
            } else {
                $targetHeight = $height;
                $targetWidth = $originalWidth / ($originalHeight / $height);
                if ($targetWidth > $width) {
                    $targetWidth = $width;
                    $targetHeight = $originalHeight / ($originalWidth / $width);
                }
            }
        }

        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
        switch ($fileType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($filePath);
                imagecopyresampled(
                    $targetImage,
                    $sourceImage,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $originalWidth,
                    $originalHeight
                );
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($filePath);
                imagealphablending($targetImage, false);
                imagesavealpha($targetImage, true);
                $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
                imagecopyresampled(
                    $targetImage,
                    $sourceImage,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $originalWidth,
                    $originalHeight
                );
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($filePath);
                imagecopyresampled(
                    $targetImage,
                    $sourceImage,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $originalWidth,
                    $originalHeight
                );
                break;
        }

        if (function_exists('exif_read_data')) {
            $targetImage = imagerotate(
                $targetImage,
                array_values([0, 0, 0, 180, 0, 0, -90, 0, 90])[@exif_read_data($filePath)['Orientation'] ?: 0],
                0
            );
        }

        ob_start();

        switch ($fileType) {
            case 'image/jpeg':
                imagejpeg($targetImage);
                break;
            case 'image/png':
                imagepng($targetImage);
                break;
            case 'image/gif':
                imagegif($targetImage);
                break;
        }
        $contents = ob_get_contents();
        ob_end_clean();
        imagedestroy($targetImage);

        return $contents;
    }

    /**
     * @param Attachment $attachment
     *
     * @return bool
     */
    protected function checkAttachment(Attachment $attachment): bool
    {
        return $this->getAcl()->checkEntity($attachment);
    }

    /**
     * @param string $size
     *
     * @return array|null
     */
    protected function getImageSize(string $size): ?array
    {
        // get sizes
        $sizes = $this->getMetadata()->get(['app', 'imageSizes'], []);

        return isset($sizes[$size]) ? $sizes[$size] : null;
    }
}
