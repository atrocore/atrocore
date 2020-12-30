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

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Attachment;
use Espo\Entities\User;

/**
 * Class UploadDir
 *
 * @package Treo\Core\FileStorage\Storages
 */
class UploadDir extends Base
{
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
     * @inheritDoc
     */
    public function getDownloadUrl(Attachment $attachment): string
    {
        $url = '?entryPoint=download&id=' . $attachment->get('id');

        /** @var User $user */
        $user = $this->getInjection('entityManager')->getUser();

        if (!empty($user) && !empty($user->get('portalId'))) {
            $url .= '&portalId=' . $user->get('portalId');
        }

        return $url;
    }

    /**
     * @inheritDoc
     */
    public function hasDownloadUrl(Attachment $attachment): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getThumbs(Attachment $attachment): array
    {
        $result = [];
        foreach ($this->getMetadata()->get(['app', 'imageSizes'], []) as $size => $params) {
            $result[$size] = $this->getConfig()->get('thumbsPath', 'upload/thumbs/') . $attachment->getStorageThumbPath() . '/' . $size . '/' . $attachment->get("name");
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('config');
        $this->addDependency('entityManager');
        $this->addDependency('Thumb');
        $this->addDependency('metadata');
    }

    /**
     * @param Attachment $attachment
     *
     * @return string
     */
    protected function getFilePath(Attachment $attachment): string
    {
        return $this->getConfig()->get('filesPath', 'upload/files/') . $attachment->getStorageFilePath() . '/' . $attachment->get("name");
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

    /**
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getInjection('config');
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }
}
