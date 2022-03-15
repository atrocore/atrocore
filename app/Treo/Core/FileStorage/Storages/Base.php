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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

namespace Treo\Core\FileStorage\Storages;

use \Espo\Core\Interfaces\Injectable;
use Espo\Entities\Attachment;

/**
 * Class Base
 * @package Treo\Core\FileStorage\Storages
 */
abstract class Base implements Injectable
{
    /**
     * @var array
     */
    protected $dependencyList = [];

    /**
     * @var array
     */
    protected $injections = array();

    /**
     * @param $name
     * @param $object
     */
    public function inject($name, $object)
    {
        $this->injections[$name] = $object;
    }

    /**
     * Base constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Init method
     */
    protected function init()
    {
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    /**
     * @param $name
     */
    protected function addDependency($name)
    {
        $this->dependencyList[] = $name;
    }

    /**
     * @param array $list
     */
    protected function addDependencyList(array $list)
    {
        foreach ($list as $item) {
            $this->addDependency($item);
        }
    }

    /**
     * @return array
     */
    public function getDependencyList()
    {
        return $this->dependencyList;
    }

    /**
     * @param Attachment $attachment
     *
     * @return string
     */
    abstract public function getDownloadUrl(Attachment $attachment): string;

    /**
     * @param Attachment $attachment
     * @return mixed
     */
    abstract public function unlink(Attachment $attachment);

    /**
     * @param Attachment $attachment
     * @return mixed
     */
    abstract public function getContents(Attachment $attachment);

    /**
     * @param Attachment $attachment
     * @return mixed
     */
    abstract public function isFile(Attachment $attachment);

    /**
     * @param Attachment $attachment
     * @param $contents
     * @return mixed
     */
    abstract public function putContents(Attachment $attachment, $contents);

    /**
     * @param Attachment $attachment
     * @return mixed
     */
    abstract public function getLocalFilePath(Attachment $attachment);

    /**
     * @param Attachment $attachment
     *
     * @return array
     */
    abstract public function getThumbs(Attachment $attachment): array;
}
