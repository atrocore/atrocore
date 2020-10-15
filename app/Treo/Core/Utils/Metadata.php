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
 * Website: https://treolabs.com
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

use Espo\Core\Utils\Metadata as Base;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\Utils\DataUtil;
use Treo\Core\ModuleManager\Manager as ModuleManager;
use Treo\Core\EventManager\Manager as EventManager;
use Treo\Core\EventManager\Event;

/**
 * Metadata class
 */
class Metadata extends Base
{
    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @var string
     */
    private $treoCacheFile = 'data/cache/metadata.json';

    /**
     * Metadata constructor.
     *
     * @param FileManager   $fileManager
     * @param ModuleManager $moduleManager
     * @param EventManager  $eventManager
     * @param bool          $useCache
     */
    public function __construct(
        FileManager $fileManager,
        ModuleManager $moduleManager,
        EventManager $eventManager,
        bool $useCache = false
    ) {
        parent::__construct($fileManager, $useCache);

        $this->moduleManager = $moduleManager;
        $this->eventManager = $eventManager;

        // add hidden paths
        $this->frontendHiddenPathList[] = ['app', 'services'];
    }

    /**
     * @return bool
     */
    public function isCached()
    {
        if (!$this->useCache) {
            return false;
        }

        if (file_exists($this->treoCacheFile)) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getEntityPath($entityName, $delim = '\\')
    {
        $path = parent::getEntityPath($entityName, $delim);

        // for espo classes
        if (!class_exists($path)) {
            $path = implode($delim, ['Espo', 'Entities', Util::normilizeClassName(ucfirst($entityName))]);
        }

        return $path;
    }

    /**
     * @inheritdoc
     */
    public function getRepositoryPath($entityName, $delim = '\\')
    {
        $path = parent::getRepositoryPath($entityName, $delim);

        // for espo classes
        if (!class_exists($path)) {
            $path = implode($delim, ['Espo', 'Repositories', Util::normilizeClassName(ucfirst($entityName))]);
        }

        return $path;
    }

    /**
     * @inheritdoc
     */
    public function getScopePath($scopeName, $delim = '/')
    {
        $moduleName = $this->getScopeModuleName($scopeName);

        // set treo name
        if ($moduleName == 'TreoCore') {
            $moduleName = 'Treo';
        }

        $path = ($moduleName !== false) ? $moduleName : 'Treo';

        if ($delim != '/') {
            $path = str_replace('/', $delim, $path);
        }

        return $path;
    }

    /**
     * Get modules
     *
     * @return array
     */
    public function getModules(): array
    {
        return $this->moduleManager->getModules();
    }

    /**
     * @inheritdoc
     */
    public function init($reload = false)
    {
        $this->data = json_decode(json_encode($this->getObjData($reload)), true);
    }

    /**
     * Get additional field lists
     *
     * @param string $scope
     * @param string $field
     *
     * @return array
     */
    public function getFieldList(string $scope, string $field): array
    {
        // prepare result
        $result = [];

        // get field data
        $fieldData = $this->get("entityDefs.$scope.fields.$field");

        if (!empty($fieldData)) {
            // prepare result
            $result[$field] = $fieldData;

            $additionalFields = $this
                ->getMetadataHelper()
                ->getAdditionalFieldList($field, $fieldData, $this->get("fields"));
            if (!empty($additionalFields)) {
                // prepare result
                $result = $result + $additionalFields;
            }
        }
        return $result;
    }

    /**
     * Is module installed
     *
     * @param string $id
     *
     * @return bool
     */
    public function isModuleInstalled(string $id): bool
    {
        foreach ($this->getModules() as $name => $module) {
            if ($name == $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param bool $reload
     */
    protected function objInit($reload = false)
    {
        // prepare reload
        if (!$this->useCache) {
            $reload = true;
        }

        if (!$reload && file_exists($this->treoCacheFile)) {
            $this->objData = json_decode(file_get_contents($this->treoCacheFile));
        } else {
            // load espo
            $content = $this->unify(CORE_PATH . '/Espo/Resources/metadata');

            // load treo
            $content = DataUtil::merge($content, $this->unify(CORE_PATH . '/Treo/Resources/metadata'));

            // load modules
            foreach ($this->getModules() as $module) {
                $module->loadMetadata($content);
            }

            // load custom
            $content = DataUtil::merge($content, $this->unify('custom/Espo/Custom/Resources/metadata'));

            $this->objData = $this->addAdditionalFieldsObj($content);

            if ($this->useCache) {
                // create cache dir
                if (!file_exists('data/cache')) {
                    mkdir('data/cache');
                }

                // create metadata cache file
                file_put_contents($this->treoCacheFile, json_encode($this->objData));
            }
        }

        // dispatch an event
        $event = $this
            ->getEventManager()
            ->dispatch('Metadata', 'modify', new Event(['data' => json_decode(json_encode($this->objData), true)]));

        // set object data
        $this->objData = json_decode(json_encode($event->getArgument('data')));

        // clearing metadata
        $this->clearingMetadata();
    }

    /**
     * Clearing metadata
     */
    protected function clearingMetadata()
    {
        foreach ($this->objData->entityDefs as $scope => $rows) {
            if (isset($rows->fields)) {
                foreach ($rows->fields as $field => $params) {
                    if (!isset($params->type)) {
                        unset($this->objData->entityDefs->$scope->fields->$field);
                    }
                }
            }
        }
    }

    /**
     * @param string $path
     *
     * @return \stdClass
     */
    private function unify(string $path): \stdClass
    {
        return $this->getObjUnifier()->unify('metadata', $path, true);
    }

    /**
     * @return EventManager
     */
    private function getEventManager(): EventManager
    {
        return $this->eventManager;
    }
}
