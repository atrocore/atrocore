<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

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

        $this->show($_GET['id'], $_GET['type'] ?? 'attachment', $size);
    }

    /**
     * @param mixed  $id
     * @param string $type
     * @param mixed  $size
     * @param bool   $disableAccessCheck
     *
     * @throws Error
     * @throws Forbidden
     * @throws NotFound
     */
    protected function show($id, $type, $size, $disableAccessCheck = false)
    {
        $attachment = $this->getAttachment($type, $id);
        if (empty($attachment)) {
            throw new NotFound();
        }

        if (!$disableAccessCheck && !$this->checkAttachment($attachment)) {
            throw new Forbidden();
        }

        // delegate to DAM
        if (class_exists('\Dam\Core\Preview\Base')) {
            return \Dam\Core\Preview\Base::init($attachment, $size, $this->getContainer())->show();
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

    /**
     * @param string $type
     * @param string $id
     *
     * @return Attachment|null
     * @throws Error
     */
    protected function getAttachment($type, $id): ?Attachment
    {
        switch ($type) {
            case "attachment" :
                return $this->getEntityManager()->getEntity("Attachment", $id);
                break;
            case "asset":
            default:
                $asset = $this->getEntityManager()->getEntity("Asset", $id);
                return $asset->get("file");
        }
    }
}
