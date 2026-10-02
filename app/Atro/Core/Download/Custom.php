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

use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Entities\File;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Util;
use Espo\ORM\EntityManager;
use Imagick;

class Custom
{
    private const DEFAULT_ALLOWED_FORMATS = ['jpeg', 'jpg', 'png', 'webp'];
    private const MIN_DIMENSION = 1;
    private const DEFAULT_MAX_DIMENSION = 5000;
    private const DEFAULT_MAX_MEMORY = 256 * 1024 * 1024;
    private const DEFAULT_MAX_MAP = 512 * 1024 * 1024;
    private const DEFAULT_MAX_AREA = 64_000_000;

    protected File $file;
    protected Imagick $imagick;
    protected ?string $scale;
    protected ?int $width;
    protected ?int $height;
    protected ?int $quality;
    protected ?string $format;
    protected Config $config;
    protected EntityManager $entityManager;

    public function __construct(Container $container)
    {
        $this->config = $container->get('config');
        $this->entityManager = $container->get('entityManager');
    }

    public function convert(File $file, array $params): string
    {
        $this->setFile($file);
        $this->setParams($params);

        Util::createDir('public' . DIRECTORY_SEPARATOR .  $this->getDirPath());

        $this->resize()->quality()->format();
        $this->imagick->writeImage($this->getFilePath());

        return $this->getDirPath() . DIRECTORY_SEPARATOR . $this->getName();
    }

    protected function setFile(File $file): Custom
    {
        $filePath = $file->getFilePath();
        if (!file_exists($filePath)) {
            throw new NotFound();
        }

        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, (int)$this->config->get('imagickMemoryLimit', self::DEFAULT_MAX_MEMORY));
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, (int)$this->config->get('imagickMapLimit', self::DEFAULT_MAX_MAP));
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_AREA, (int)$this->config->get('imagickAreaLimit', self::DEFAULT_MAX_AREA));

        $this->file = $file;
        $this->imagick = new \Imagick($filePath);

        return $this;
    }

    protected function setParams(array $params): Custom
    {
        $this->width = $params['width'] ? $this->clampDimension((int)$params['width']) : null;
        $this->height = $params['height'] ? $this->clampDimension((int)$params['height']) : null;
        $this->quality = $params['quality'] ? (int)$params['quality'] : null;
        $this->format = strtolower(trim((string)($params['format'] ?? ''))) ?: 'jpeg';
        $this->scale = $params['scale'] ?? null;

        $allowedFormats = array_map('strtolower', (array)$this->config->get('imageConversionAllowedFormats', self::DEFAULT_ALLOWED_FORMATS));
        if (!in_array($this->format, $allowedFormats, true)) {
            throw new BadRequest('Unsupported format.');
        }

        return $this;
    }

    protected function clampDimension(int $value): int
    {
        $max = (int)$this->config->get('imageConversionMaxDimension', self::DEFAULT_MAX_DIMENSION);

        return max(self::MIN_DIMENSION, min($max, $value));
    }

    protected function getDirPath(): string
    {
        if (empty($this->file)) {
            throw new Error('Attachment is required for converter.');
        }

        return $this->config->get('renditionPath', 'upload/rendition/') . $this->file->get('id') . DIRECTORY_SEPARATOR . $this->createSubDir();
    }

    protected function getFilePath(): string
    {
        return 'public' . DIRECTORY_SEPARATOR . $this->getDirPath() . DIRECTORY_SEPARATOR . $this->getName();
    }

    protected function createSubDir(): string
    {
        $key = $this->config->get('passwordSalt', '') . '_' . $this->width . '_' . $this->height . '_' . $this->quality . '_' . $this->scale . '_' . $this->format;

        return md5($key);
    }

    protected function getImageWidth(): int
    {
        return $this->imagick->getImageWidth();
    }

    protected function getImageHeight(): int
    {
        return $this->imagick->getImageHeight();
    }

    protected function getName(): string
    {
        $name = explode(".", basename($this->file->get("name")));
        array_pop($name);
        $name[] = $this->format;

        return str_replace("\"", "\\\"", implode(".", $name));
    }

    protected function getType(): string
    {
        return $this->format === "png" ? "image/png" : "image/jpeg";
    }

    protected function resize(): Custom
    {
        switch ($this->scale) {
            case "resize":
                if (!empty($this->width) && !empty($this->height)) {
                    $this->imagick->resizeImage(
                        (int)$this->width,
                        (int)$this->height,
                        Imagick::FILTER_HAMMING,
                        1, false
                    );
                }
                break;
            case "byWidth":
                if (!empty($this->width)) {
                    $this->imagick->resizeImage(
                        (int)$this->width,
                        1000000000,
                        Imagick::FILTER_HAMMING,
                        1,
                        true
                    );
                }
                break;
            case "byHeight" :
                if (!empty($this->height)) {
                    $this->imagick->resizeImage(
                        1000000000,
                        (int)$this->height,
                        Imagick::FILTER_HAMMING,
                        1,
                        true
                    );
                }
                break;
        }

        return $this;
    }

    protected function quality(): Custom
    {
        if (in_array($this->format, ['jpeg', 'webp'])) {
            $this->imagick->setImageCompressionQuality((int)$this->quality);
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