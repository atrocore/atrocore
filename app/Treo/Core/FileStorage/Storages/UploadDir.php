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

namespace Treo\Core\FileStorage\Storages;

use Espo\Core\Exceptions\Error;
use Treo\Core\FilePathBuilder;
use Treo\Entities\Attachment;

/**
 * Class UploadDir
 *
 * @package Treo\Core\FileStorage\Storages
 */
class UploadDir extends Base
{
    const BASE_PATH = "data/upload/files/";
    const BASE_THUMB_PATH = "data/upload/thumbs/";
    /**
     * @var array
     */
    protected $dependencyList = ['fileManager', 'filePathBuilder'];

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    public function unlink(Attachment $attachment)
    {
        return $this->getFileManager()->unlink($this->getFilePath($attachment));
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    public function isFile(Attachment $attachment)
    {
        return $this->getFileManager()->isFile($this->getFilePath($attachment));
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    public function getContents(Attachment $attachment)
    {
        return $this->getFileManager()->getContents($this->getFilePath($attachment));
    }

    /**
     * @param Attachment $attachment
     * @param            $contents
     *
     * @return mixed
     */
    public function putContents(Attachment $attachment, $contents)
    {
        return $this->getFileManager()->putContents($this->getFilePath($attachment), $contents);
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed|string
     */
    public function getLocalFilePath(Attachment $attachment)
    {
        return $this->getFilePath($attachment);
    }

    /**
     * @param Attachment $attachment
     *
     * @return mixed|void
     * @throws Error
     */
    public function getDownloadUrl(Attachment $attachment)
    {
        throw new Error();
    }

    /**
     * @param Attachment $attachment
     *
     * @return bool|mixed
     */
    public function hasDownloadUrl(Attachment $attachment)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('entityManager');
    }

    /**
     * @param Attachment $attachment
     *
     * @return string
     */
    protected function getFilePath(Attachment $attachment): string
    {
        $storage = $attachment->get('storageFilePath');

        if (!$storage) {
            $storage = $this->getPathBuilder()->createPath(FilePathBuilder::UPLOAD);
            $attachment->set('storageFilePath', $storage);
        }

        return self::BASE_PATH . "{$storage}/" . $attachment->get('name');
    }

    /**
     * @return mixed
     */
    protected function getPathBuilder()
    {
        return $this->getInjection('filePathBuilder');
    }

    /**
     * @return mixed
     */
    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }
}
