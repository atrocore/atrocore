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

namespace Atro\Core\Download;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Injectable;
use Espo\Core\Utils\Util;
use Espo\Entities\Attachment;
use Imagick;

class Custom extends Injectable
{
    /**
     * @var Imagick
     */
    protected $imagick;

    /**
     * @var string
     */
    protected $scale;

    /**
     * @var integer
     */
    protected $width;

    /**
     * @var integer
     */
    protected $height;

    /**
     * @var integer
     */
    protected $quality;

    /**
     * @var string
     */
    protected $format;

    /**
     * @var Attachment
     */
    protected $attachment;

    public function __construct()
    {
        $this->addDependency('config');
        $this->addDependency('entityManager');
    }

    public function setAttachment(Attachment $attachment): Custom
    {
        $filePath = $this->getInjection('entityManager')->getRepository('Attachment')->getFilePath($attachment);
        if (!file_exists($filePath)) {
            throw new NotFound();
        }

        $this->attachment = $attachment;
        $this->imagick = new \Imagick($filePath);

        return $this;
    }

    public function setParams(array $params): Custom
    {
        foreach ($params as $propName => $value) {
            if (!property_exists($this, $propName)) {
                continue;
            }

            $this->{$propName} = $value;
        }

        return $this;
    }

    public function getDirPath(): string
    {
        if (empty($this->attachment)) {
            throw new Error('Attachment is required for converter.');
        }

        return $this->getInjection('config')->get('renditionPath', 'upload/rendition/') . $this->attachment->get('id') . '/' . $this->createSubDir();
    }

    public function getFilePath(): string
    {
        return $this->getDirPath() . '/' . $this->getName();
    }

    public function convert(): Custom
    {
        if (file_exists($this->getFilePath())) {
            return $this;
        }

        Util::createDir($this->getDirPath());

        $this->resize()->quality()->format();
        $this->imagick->writeImage($this->getFilePath());

        return $this;
    }

    public function createSubDir(): string
    {
        $key = $this->getInjection('config')->get('passwordSalt', '') . '_' . $this->width . '_' . $this->height . '_' . $this->quality . '_' . $this->scale . '_' . $this->format;

        return md5($key);
    }

    public function getImageWidth(): int
    {
        return $this->imagick->getImageWidth();
    }

    public function getImageHeight(): int
    {
        return $this->imagick->getImageHeight();
    }

    public function getName(): string
    {
        $name = explode(".", $this->attachment->get("name"));
        array_pop($name);
        $name[] = $this->format === "png" ? "png" : "jpeg";

        return str_replace("\"", "\\\"", implode(".", $name));
    }

    public function getType(): string
    {
        return $this->format === "png" ? "image/png" : "image/jpeg";
    }

    protected function resize(): Custom
    {
        switch ($this->scale) {
            case "resize":
                $this->imagick->resizeImage(
                    (int)$this->width,
                    (int)$this->height,
                    Imagick::FILTER_HAMMING,
                    1, false
                );
                break;
            case "byWidth":
                $this->imagick->resizeImage(
                    (int)$this->width,
                    1000000000,
                    Imagick::FILTER_HAMMING,
                    1,
                    true
                );
                break;
            case "byHeight" :
                $this->imagick->resizeImage(
                    1000000000,
                    (int)$this->height,
                    Imagick::FILTER_HAMMING,
                    1,
                    true
                );
                break;
        }

        return $this;
    }

    protected function quality(): Custom
    {
        switch (true) {
            case $this->format === "jpeg":
                $this->imagick->setImageCompressionQuality((int)$this->quality);
                break;
            case $this->format === "png" :
                break;
        }

        return $this;
    }

    protected function format(): Custom
    {
        if ($this->format === "jpeg") {
            $this->imagick->setBackgroundColor("#ffffff");
            $this->imagick = $this->imagick->flattenImages();
        }
        $this->imagick->setImageFormat($this->format);

        return $this;
    }
}