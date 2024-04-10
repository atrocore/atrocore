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

namespace Atro\Core;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Imagick;

class ImageResize
{
    const CROP_TOP        = 1;
    const CROP_CENTER     = 2;
    const CROP_BOTTOM     = 3;
    const CROP_LEFT       = 4;
    const CROP_RIGHT      = 5;
    const CROP_TOP_CENTER = 6;

    /**@var $image Imagick * */
    protected $image;

    protected $sourceX;
    protected $sourceY;
    protected $destW;
    protected $destH;
    protected $sourceW;
    protected $sourceH;

    /**
     * @param string $filename
     * @return $this
     * @throws \ImagickException
     */
    public function load(string $filename)
    {
        if (!defined('IMAGETYPE_WEBP')) {
            define('IMAGETYPE_WEBP', 18);
        }
        if (!$filename) {
            throw new BadRequest('File does not exist');
        }

        $this->image = new Imagick($filename);

        $this->sourceH = $this->image->getImageHeight();
        $this->sourceW = $this->image->getImageWidth();

        return $this;
    }

    /**
     * @param      $max_width
     * @param      $max_height
     * @param bool $upscale
     * @return $this
     */
    public function resizeToBestFit($max_width, $max_height, $upscale = false)
    {
        $res = true;

        if ($this->useUpscale($upscale, [$max_width, $max_height])) {
            $res = $this->image->resizeImage($max_width, $max_height, Imagick::FILTER_HAMMING, 1, true);
        }

        if (!$res) {
            throw new Error("Can't resize image");
        }

        return $this;
    }

    /**
     * @param      $width
     * @param      $height
     * @param bool $upscale
     * @return $this
     */
    public function resize($width, $height, $upscale = false)
    {
        $res = true;

        if ($this->useUpscale($upscale, [$width, $height])) {
            $res = $this->image->resizeImage($width, $height, Imagick::FILTER_HAMMING, 1, true);
        }

        if (!$res) {
            throw new Error("Can't resize image");
        }

        return $this;
    }

    /**
     * @param      $width
     * @param      $height
     * @param bool $upscale
     * @param int  $position
     * @return $this
     * @throws Error
     */
    public function crop($width, $height, $upscale = false, $position = self::CROP_CENTER)
    {
        if (!$upscale) {
            if ($width > $this->getSourceWidth()) {
                $width = $this->getSourceWidth();
            }

            if ($height > $this->getSourceHeight()) {
                $height = $this->getSourceHeight();
            }
        }

        $ratio_source = $this->image->getImageWidth() / $this->image->getImageHeight();
        $ratio_dest   = $width / $height;

        if ($ratio_dest < $ratio_source) {
            $this->resizeToHeight($height, $upscale);

            $excess_width = ($this->getDestWidth() - $width) / $this->getDestWidth() * $this->getSourceWidth();

            $this->sourceW = $this->getSourceWidth() - $excess_width;
            $this->sourceX = $this->getCropPosition($excess_width, $position);

            $this->destW = $width;
        } else {
            $this->resizeToWidth($width, $upscale);

            $excess_height = ($this->getDestHeight() - $height) / $this->getDestHeight() * $this->getSourceHeight();

            $this->sourceH = $this->getSourceHeight() - $excess_height;
            $this->sourceY = $this->getCropPosition($excess_height, $position);

            $this->destH = $height;
        }

        if (!$this->image->cropImage((int)$this->sourceW, (int)$this->sourceH, (int)$this->sourceX, (int)$this->sourceY)) {
            throw new Error("Can't crop image");
        }

        if (!$this->image->resizeImage($this->destW, $this->destH, Imagick::FILTER_HAMMING, 1)) {
            throw new Error("Can't resize image");
        }

        return $this;
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function save(string $fileName)
    {
        return $this->image->writeImage($fileName);
    }

    /**
     * @param bool  $use
     * @param array $params
     * @return bool
     */
    protected function useUpscale(bool $use, array $params)
    {
        list($w, $h) = $params;

        if (!$use && ($w > $this->image->getImageWidth() || $h > $this->image->getImageHeight())) {
            return false;
        }

        return true;
    }

    /**
     * @param      $height
     * @param bool $allow_enlarge
     * @return $this
     */
    protected function resizeToHeight($height, $allow_enlarge = false)
    {
        $ratio = $height / $this->image->getImageHeight();
        $width = $this->image->getImageWidth() * $ratio;

        $this->setImageProps($width, $height, $allow_enlarge);

        return $this;
    }

    /**
     * @param $width
     * @param $height
     * @param $upscale
     * @return $this
     */
    protected function setImageProps($width, $height, $upscale)
    {
        if (!$upscale) {
            if ($width > $this->getSourceWidth() || $height > $this->getSourceHeight()) {
                $width  = $this->getSourceWidth();
                $height = $this->getSourceHeight();
            }
        }

        $this->sourceX = 0;
        $this->sourceY = 0;

        $this->destW = $width;
        $this->destH = $height;

        $this->sourceW = $this->getSourceWidth();
        $this->sourceH = $this->getSourceHeight();

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getSourceWidth()
    {
        return $this->sourceW;
    }

    /**
     * @return mixed
     */
    protected function getSourceHeight()
    {
        return $this->sourceH;
    }

    /**
     * @return mixed
     */
    protected function getDestWidth()
    {
        return $this->destW;
    }

    /**
     * @return mixed
     */
    protected function getDestHeight()
    {
        return $this->destH;
    }

    /**
     * @param     $expectedSize
     * @param int $position
     * @return float|int
     */
    protected function getCropPosition($expectedSize, $position = self::CROP_CENTER)
    {
        $size = 0;
        switch ($position) {
            case self::CROP_BOTTOM:
            case self::CROP_RIGHT:
                $size = $expectedSize;
                break;
            case self::CROP_CENTER:
                $size = $expectedSize / 2;
                break;
            case self::CROP_TOP_CENTER:
                $size = $expectedSize / 4;
                break;
        }

        return $size;
    }

    /**
     * @param      $width
     * @param bool $upscale
     * @return $this
     */
    public function resizeToWidth($width, $upscale = false)
    {
        $ratio  = $width / $this->getSourceWidth();
        $height = $this->getSourceHeight() * $ratio;

        $this->setImageProps($width, $height, $upscale);

        return $this;
    }

}