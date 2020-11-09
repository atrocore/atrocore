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

namespace Multilang\Services;

use Espo\Core\Utils\Json;
use Treo\Core\Utils\Layout;
use Treo\Core\Utils\Metadata;
use Treo\Core\Utils\Util;
use Treo\Services\AbstractService;

/**
 * Class Multilang
 */
class Multilang extends AbstractService
{
    public const SKIP_ENTITIES = ['ProductAttributeValue'];

    /**
     * @return bool
     */
    public function updateLayouts(): bool
    {
        // exit is multi-lang inactive
        if (!$this->getConfig()->get('isMultilangActive', false)) {
            return false;
        }

        foreach ($this->getMetadata()->get(['entityDefs'], []) as $scope => $data) {
            if (!isset($data['fields']) || in_array($scope, self::SKIP_ENTITIES, true)) {
                continue 1;
            }

            $this->updateLayout($scope, 'detail');

            $this->updateLayout($scope, 'detailSmall');
        }

        return true;
    }

    /**
     * @param string $scope
     * @param string $layout
     *
     * @return bool
     */
    protected function updateLayout(string $scope, string $layout): bool
    {
        // Find multi-lang fields
        $multiLangFields = [];
        foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields'], []) as $field => $data) {
            if (!empty($data['isMultilang'])) {
                $multiLangFields[] = $field;
            }
        }

        // exit if no multi-lang fields
        if (empty($multiLangFields)) {
            return true;
        }

        $needSave = false;

        // get exists
        $exists = $this->getLayoutFields($scope, $layout);

        // get layout data
        $layoutData = Json::decode($this->getLayout()->get($scope, $layout), true);

        $result = [];
        foreach ($layoutData as $k => $panel) {
            // set old data
            $result[$k] = $panel;
            $result[$k]['rows'] = [];

            // skip if no rows
            if (empty($panel['rows'])) {
                continue 1;
            }
            foreach ($panel['rows'] as $row) {
                // find multi-lang fields for injecting them to layout
                $multiLangForSet = [];
                foreach ($row as $field) {
                    if (!empty($field['name']) && in_array($field['name'], $multiLangFields)) {
                        $multiLangForSet[] = $field['name'];
                    }
                }
                $result[$k]['rows'][] = $row;
                if (!empty($multiLangForSet)) {
                    foreach ($this->createLangRows($scope, $multiLangForSet, $exists) as $langRow) {
                        $needSave = true;
                        $result[$k]['rows'][] = $langRow;
                    }
                }
            }
        }

        if ($needSave) {
            $this->getLayout()->set($result, $scope, $layout);
            $this->getLayout()->save();
        }

        return true;
    }

    /**
     * @param string $scope
     * @param string $layout
     *
     * @return array
     */
    protected function getLayoutFields(string $scope, string $layout): array
    {
        // get layout data
        $layoutData = Json::decode($this->getLayout()->get($scope, $layout), true);

        $result = [];
        foreach ($layoutData as $k => $panel) {
            // skip if no rows
            if (empty($panel['rows'])) {
                continue 1;
            }
            foreach ($panel['rows'] as $row) {
                foreach ($row as $field) {
                    if (!empty($field['name'])) {
                        $result[] = $field['name'];
                    }
                }

            }
        }

        return $result;
    }

    /**
     * @param string $scope
     * @param array  $fields
     * @param array  $exists
     *
     * @return array
     */
    protected function createLangRows(string $scope, array $fields, array $exists): array
    {
        // collect locales
        $locales = [];
        foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
            $locales[] = ucfirst(Util::toCamelCase(strtolower($locale)));
        }

        $result = [];
        foreach ($fields as $field) {
            $row = [];
            foreach ($locales as $locale) {
                $langField = $field . $locale;
                if (!in_array($langField, $exists)) {
                    if (!empty($row[1]) || !empty($row[0]['fullWidth'])) {
                        $result[] = $row;
                        $row = [];
                    }
                    $row[] = [
                        'name'      => $langField,
                        'fullWidth' => $this->getMetadata()->get(['entityDefs', $scope, 'fields', $langField, 'type'], 'varchar') == 'wysiwyg'
                    ];
                }
            }

            if (!empty($row)) {
                if (empty($row[1]) && empty($row[0]['fullWidth'])) {
                    $row[] = false;
                }
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @return Layout
     */
    protected function getLayout(): Layout
    {
        return $this->getContainer()->get('layout');
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
