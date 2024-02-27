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

namespace Espo\Core\Services;

use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Exceptions\BadRequest;
use Espo\Core\Interfaces\Injectable;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Class Base
 */
abstract class Base implements Injectable
{
    /**
     * @var string[]
     */
    protected $dependencies = ['config', 'entityManager', 'user', 'language', 'memoryStorage'];

    /**
     * @var array
     */
    protected $injections = [];

    /**
     * @param string $name
     * @param object $object
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

    public static function getHeader(string $name): ?string
    {
        try {
            $headers = \getallheaders();
        } catch (\Throwable $e) {
            $headers = [];
        }

        foreach ($headers as $k => $v) {
            if (strtolower($name) === strtolower($k)) {
                return $v;
            }
        }

        return null;
    }

    public static function getLanguagePrism(): ?string
    {
        $language = self::getHeader('language');
        if (!empty($GLOBALS['languagePrism'])) {
            $language = $GLOBALS['languagePrism'];
        }

        return $language;
    }

    /**
     * Init
     */
    protected function init()
    {
    }

    protected function getHeaderLanguage(): ?string
    {
        $language = self::getLanguagePrism();
        if (!empty($language)) {
            $languages = ['main'];
            if ($this->getConfig()->get('isMultilangActive')) {
                $languages = array_merge($languages, $this->getConfig()->get('inputLanguageList', []));
            }

            if (in_array($language, $languages)) {
                return $language;
            }

            throw new BadRequest('No such language is available.');
        }

        return null;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    /**
     * @param string $name
     *
     * @return void
     */
    protected function addDependency($name)
    {
        $this->dependencies[] = $name;
    }

    /**
     * @param array $list
     *
     * @return void
     */
    protected function addDependencyList(array $list)
    {
        foreach ($list as $item) {
            $this->addDependency($item);
        }
    }

    /**
     * @return string[]
     */
    public function getDependencyList()
    {
        return $this->dependencies;
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getInjection('entityManager');
    }

    /**
     * @return Config
     */
    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    /**
     * @return User
     */
    protected function getUser()
    {
        return $this->getInjection('user');
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->getInjection('memoryStorage');
    }
}

