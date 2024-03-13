<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

namespace Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;

class Helper
{
    private $metadata;

    protected $defaultNaming = 'postfix';

    /**
     * List of copied params for metadata -> 'fields' from parent items
     */
    protected $copiedDefParams = array(
        'readOnly',
        'notStorable',
        'layoutListDisabled',
        'layoutDetailDisabled',
        'massUpdateDisabled',
        'filterDisabled',
    );

    public function __construct(\Espo\Core\Utils\Metadata $metadata)
    {
        $this->metadata = $metadata;
    }

    protected function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Get field defenition by type in metadata, "fields" key
     *
     * @param  array | string $fieldDef - It can be a string or field defenition from entityDefs
     * @return array | null
     */
    public function getFieldDefsByType($fieldDef)
    {
        if (is_string($fieldDef)) {
            $fieldDef = array('type' => $fieldDef);
        }

        if (isset($fieldDef['dbType'])) {
            return $this->getMetadata()->get('fields.'.$fieldDef['dbType']);
        } else if (isset($fieldDef['type'])) {
            return $this->getMetadata()->get('fields.'.$fieldDef['type']);
        }

        return null;
    }

    public function getFieldDefsInFieldMeta($fieldDef)
    {
        $fieldDefsByType = $this->getFieldDefsByType($fieldDef);
        if (isset($fieldDefsByType['fieldDefs'])) {
            return $fieldDefsByType['fieldDefs'];
        }

        return null;
    }

    /**
     * Get link definition defined in 'fields' metadata. In linkDefs can be used as value (e.g. "type": "hasChildren") and/or variables (e.g. "entityName":"{entity}"). Variables should be defined into fieldDefs (in 'entityDefs' metadata).
     *
     * @param  string $entityName
     * @param  array  $fieldDef
     * @param  array  $linkFieldDefsByType
     * @return array | null
     */
    public function getLinkDefsInFieldMeta($entityName, $fieldDef, array $linkFieldDefsByType = null)
    {
        if (!isset($fieldDefsByType)) {
            $fieldDefsByType = $this->getFieldDefsByType($fieldDef);
            if (!isset($fieldDefsByType['linkDefs'])) {
                return null;
            }
            $linkFieldDefsByType = $fieldDefsByType['linkDefs'];
        }

        foreach ($linkFieldDefsByType as $paramName => &$paramValue) {
            if (preg_match('/{(.*?)}/', $paramValue, $matches)) {
                if (in_array($matches[1], array_keys($fieldDef))) {
                    $value = $fieldDef[$matches[1]];
                } else if (strtolower($matches[1]) == 'entity') {
                    $value = $entityName;
                }

                if (isset($value)) {
                    $paramValue = str_replace('{'.$matches[1].'}', $value, $paramValue);
                }
            }
        }

        return $linkFieldDefsByType;
    }

    /**
     * Get additional field list based on field definition in metadata 'fields'
     *
     * @param  string     $fieldName
     * @param  array     $fieldParams
     * @param  array|null $definitionList
     *
     * @return array
     */
    public function getAdditionalFieldList($fieldName, array $fieldParams, array $definitionList)
    {
        if (empty($fieldParams['type']) || empty($definitionList)) {
            return;
        }

        $fieldType = $fieldParams['type'];
        $fieldDefinition = isset($definitionList[$fieldType]) ? $definitionList[$fieldType] : null;

        if (isset($fieldDefinition) && !empty($fieldDefinition['fields']) && is_array($fieldDefinition['fields'])) {

            $copiedParams = array_intersect_key($fieldParams, array_flip($this->copiedDefParams));

            $additionalFields = array();

            //add additional fields
            foreach ($fieldDefinition['fields'] as $subFieldName => $subFieldParams) {
                $namingType = isset($fieldDefinition['naming']) ? $fieldDefinition['naming'] : $this->defaultNaming;

                $subFieldNaming = Util::getNaming($fieldName, $subFieldName, $namingType);
                $additionalFields[$subFieldNaming] = array_merge($copiedParams, $subFieldParams);
            }

            return $additionalFields;
        }

    }

}