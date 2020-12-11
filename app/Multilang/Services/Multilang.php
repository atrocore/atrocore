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
        // exit if no multi-lang fields
        if (empty($multiLangFields = $this->getMultiLangFields($scope))) {
            return true;
        }

        // prepare result
        $result = [];

        $needSave = false;

        // get exists
        $exists = $this->getLayoutFields($scope, $layout);

        // get layout data
        $layoutData = Json::decode($this->getLayout()->get($scope, $layout), true);

        // collect locales
        $locales = $this->getPreparedLocalesCodes();

        foreach ($layoutData as $k => $panel) {
            $result[$k] = $panel;

            if (isset($panel['rows']) || !empty($panel['rows'])) {
                $rows = [];
                $skip = false;

                foreach ($panel['rows'] as $key => $row) {
                    if ($skip) {
                        $skip = false;
                        continue;
                    }

                    if (empty(array_diff($row, [false]))) {
                        $rows[] = $row;
                        $needSave = true;
                        continue;
                    }

                    $newRow = [];
                    $fullWidthRow = count($row) == 1;

                    foreach ($row as $field) {
                        $newRow[] = $field;

                        if (is_array($field) && in_array($field['name'], $multiLangFields)) {
                            $localeFields = $this->getMultiLangLocalesFields($field['name']);

                            if (!empty($needToAdd = array_diff($localeFields, $exists))) {
                                $nextRow = $key != count($panel['rows']) - 1 ? $panel['rows'][$key + 1] : null;

                                if (!$fullWidthRow && !empty($nextRow)) {
                                    if (in_array(false, $nextRow)) {
                                        $item = null;
                                        foreach ($nextRow as $f) {
                                            if (is_array($f)) {
                                                $item = $f;
                                            }
                                        }

                                        if (in_array($item['name'], $localeFields)) {
                                            $newField = $field;
                                            $newField['name'] = array_shift($needToAdd);
                                            $newRow[] = $newField;
                                            $newRow[] = $item;

                                            $skip = true;
                                        }
                                    }
                                }

                                foreach ($needToAdd as $item) {
                                    $newField = $field;
                                    $newField['name'] = $item;
                                    $newRow[] = $newField;
                                }

                                $needSave = true;
                            }
                        }
                    }

                    if (!$fullWidthRow && count($newRow) % 2 != 0) {
                        if ($newRow[count($newRow) - 1] === false) {
                            array_pop($newRow);
                        } else {
                            $newRow[] = false;
                        }
                    }

                    $rows = array_merge($rows, array_chunk($newRow, $fullWidthRow ? 1 : 2));
                }

                $result[$k]['rows'] = $rows;
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
     *
     * @return array
     */
    protected function getMultiLangFields(string $scope): array
    {
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields'], []) as $field => $data) {
            if (!empty($data['isMultilang'])) {
                $result[] = $field;
            }
        }

        return $result;
    }

    /**
     * @param string $fieldName
     *
     * @return array
     */
    protected function getMultiLangLocalesFields(string $fieldName): array
    {
        $result = [];

        foreach ($this->getPreparedLocalesCodes() as $locale) {
            $result[] = $fieldName . $locale;
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getPreparedLocalesCodes(): array
    {
        $result = [];

        foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
            $result[] = ucfirst(Util::toCamelCase(strtolower($locale)));
        }

        return $result;
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
