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

namespace Espo\Core\Utils\Metadata;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Database\Converter;
use Espo\Core\Utils\File\Manager;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;

/**
 * Class OrmMetadata
 */
class OrmMetadata
{
    protected Metadata $metadata;
    protected Manager $fileManager;
    protected Config $config;

    public function __construct(Metadata $metadata, Manager $fileManager, Config $config)
    {
        $this->metadata = $metadata;
        $this->fileManager = $fileManager;
        $this->config = $config;
    }

    public function getData(): array
    {
        $converter = new Converter($this->metadata, $this->fileManager, $this->config);

        $data = $converter->process();

        $entityDefs = $this->metadata->get('entityDefs', []);

        foreach ($entityDefs as $scope => $scopeData) {
            if (!empty($scopeData['fields'])) {
                foreach ($scopeData['fields'] as $field => $fieldData) {
                    $data[$scope]['fields'][$field]['dataField'] = !empty($fieldData['dataField']);
                }
            }

            if (!empty($scopeData['links'])) {
                foreach ($scopeData['links'] as $link => $linkData) {
                    if (isset($linkData['type'])) {
                        if ($linkData['type'] == 'belongsTo' && !isset($entityDefs[$linkData['entity']]['fields']['name']) && isset($data[$scope]['fields'][$link . 'Name'])) {
                            unset($data[$scope]['fields'][$link . 'Name']);
                        }
                        if ($linkData['type'] == 'hasMany' && !isset($entityDefs[$linkData['entity']]['fields']['name']) && isset($data[$scope]['fields'][$link . 'Names'])) {
                            unset($data[$scope]['fields'][$link . 'Names']);
                        }
                    }
                }
            }
        }

        return $data;
    }

    public function get($key = null, $default = null)
    {
        return Util::getValueByKey($this->getData(), $key, $default);
    }
}