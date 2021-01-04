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

declare(strict_types=1);

namespace Treo\Core\Thumbnail;

use Espo\Core\Exceptions\Error;
use Espo\ORM\EntityManager;
use Gumlet\ImageResize;
use Treo\Core\Container;
use Treo\Core\Utils\Config;
use Treo\Core\Utils\File\Manager;
use Treo\Core\Utils\Metadata;
use Espo\Entities\Attachment;

/**
 * Class Image
 */
class Image
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var array
     */
    protected $imageSizes;

    /**
     * Image constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->imageSizes = $this->getMetadata()->get(['app', 'imageSizes'], []);
    }

    /**
     * @param string $path
     *
     * @return Attachment|null
     * @throws Error
     * @throws \Gumlet\ImageResizeException
     */
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

    /**
     * @param Attachment $attachment
     * @param string     $size
     *
     * @return bool
     * @throws Error
     * @throws \Gumlet\ImageResizeException
     */
    public function createThumbnail(Attachment $attachment, string $size): bool
    {
        if (empty($attachment->getThumbPath($size)) || empty($attachment->getFilePath())) {
            return false;
        }

        $image = new ImageResize($this->getImageFilePath($attachment));

        if (!$this->imageSizes[$size]) {
            throw new Error('Wrong file size');
        }

        list($w, $h) = $this->imageSizes[$size];

        $image->resizeToBestFit($w, $h);

        return $this->getFileManager()->putContents($attachment->getThumbPath($size), $image->getImageAsString());
    }

    /**
     * @param Attachment $attachment
     *
     * @return string
     */
    protected function getImageFilePath(Attachment $attachment): string
    {
        return $attachment->getFilePath();
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    /**
     * @return Manager
     */
    protected function getFileManager(): Manager
    {
        return $this->container->get('fileManager');
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    /**
     * Get config
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }
}