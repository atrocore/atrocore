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

namespace Espo\Entities;

use Espo\Core\ORM\Entity as Base;

/**
 * Class Attachment
 */
class Attachment extends Base
{
    /**
     * @return string
     */
    public function _getStorage()
    {
        return !empty($this->valuesContainer['storage']) ? $this->valuesContainer['storage'] : "UploadDir";
    }

    public function getFilePath(): string
    {
        return $this->entityManager->getRepository($this->getEntityType())->getFilePath($this);
    }

    public function getThumbPath(string $size): string
    {
        $data = $this->entityManager->getRepository($this->getEntityType())->getAttachmentPathsData($this);

        return !empty($data['thumbs'][$size]) ? $data['thumbs'][$size] : '';
    }

    public function getStorageFilePath(): string
    {
        return (string)$this->get('storageFilePath');
    }

    public function getStorageThumbPath(): string
    {
        return empty($this->get('storageThumbPath')) ? $this->getStorageFilePath() : (string)$this->get('storageThumbPath');
    }

    public function isPrivate(): bool
    {
        return !empty($this->get('private'));
    }

    public function setName($name)
    {
        $baseFileInfo = pathinfo($this->get("name"));
        $this->set("name", $name . "." . $baseFileInfo['extension']);

        return $this;
    }

    public function getAsset()
    {
        return $this->entityManager->getRepository($this->getEntityType())->getAsset($this);
    }
}
