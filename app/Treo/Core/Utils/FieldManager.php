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

namespace Treo\Core\Utils;

use Espo\Core\Utils\FieldManager as EspoFieldManager;
use Espo\Core\Utils\Metadata\Helper as MetadataHelper;
use Espo\Core\Utils\FieldManager\Hooks\Base as BaseHook;
use Treo\Traits\ContainerTrait;

/**
 * FieldManager util
 */
class FieldManager extends EspoFieldManager
{
    use ContainerTrait;

    /**
     *
     * @var MetadataHelper
     */
    protected $metadataHelper = null;

    /**
     * Construct
     */
    public function __construct()
    {
        // blocking parent construct
    }

    /**
     * Get attribute list by type
     *
     * @param string $scope
     * @param string $name
     * @param string $type
     *
     * @return array
     */
    protected function getAttributeListByType(string $scope, string $name, string $type): array
    {
        $fieldType = $this->getMetadata()->get('entityDefs.' . $scope . '.fields.' . $name . '.type');

        if (!$fieldType) {
            return [];
        }

        $defs = $this->getMetadata()->get('fields.' . $fieldType);
        if (!$defs) {
            return [];
        }

        if (is_object($defs)) {
            $defs = get_object_vars($defs);
        }

        $fieldList = [];
        if (isset($defs[$type . 'Fields'])) {
            $list = $defs[$type . 'Fields'];
            $naming = 'suffix';
            if (isset($defs['naming'])) {
                $naming = $defs['naming'];
            }
            if ($naming == 'prefix') {
                foreach ($list as $f) {
                    $fieldList[] = $f . ucfirst($name);
                }
            } else {
                foreach ($list as $f) {
                    $fieldList[] = $name . ucfirst($f);
                }
            }
        } else {
            if ($type == 'actual') {
                $fieldList[] = $name;
            }
        }

        return $fieldList;
    }

    /**
     * Get actual attribute list
     *
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    public function getActualAttributeList($scope, $name)
    {
        return $this->getAttributeListByType($scope, $name, 'actual');
    }

    /**
     * Get not actual attribute list
     *
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    public function getNotActualAttributeList($scope, $name)
    {
        return $this->getAttributeListByType($scope, $name, 'notActual');
    }

    /**
     * Get attribute list
     *
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    public function getAttributeList($scope, $name)
    {
        // prepare data
        $actualAttributeList = $this->getActualAttributeList($scope, $name);
        $notActualAttributeList = $this->getNotActualAttributeList($scope, $name);

        return array_merge($actualAttributeList, $notActualAttributeList);
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->getContainer()->get('metadata');
    }

    /**
     * Get language
     *
     * @return Language
     */
    protected function getLanguage()
    {
        return $this->getContainer()->get('language');
    }

    /**
     * Get base language
     *
     * @return mixed
     */
    protected function getBaseLanguage()
    {
        return $this->getContainer()->get('baseLanguage');
    }

    /**
     * Get metadata helper
     *
     * @return MetadataHelper
     */
    protected function getMetadataHelper()
    {
        if (is_null($this->metadataHelper)) {
            $this->metadataHelper = new MetadataHelper($this->getMetadata());
        }

        return $this->metadataHelper;
    }

    /**
     * Get default language
     *
     * @return Language
     */
    protected function getDefaultLanguage()
    {
        return $this->getContainer()->get('defaultLanguage');
    }

    /**
     * Get hook for fields
     *
     * @param $type
     *
     * @return BaseHook|null
     */
    protected function getHook($type)
    {
        // prepare hook
        $hook = null;

        // get class name
        $className = $this->getMetadata()->get(['fields', $type, 'hookClassName']);

        if (!empty($className) && class_exists($className)) {
            // create hook
            $hook = new $className();

            // inject dependencies
            foreach ($hook->getDependencyList() as $name) {
                $hook->inject($name, $this->getContainer()->get($name));
            }
        }

        return $hook;
    }
}
