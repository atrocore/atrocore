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

namespace Multilang\Services;

use Espo\Core\Utils\Json;
use Treo\Core\Utils\Layout;
use Treo\Core\Utils\Metadata;
use Treo\Services\AbstractService;

/**
 * Class Multilang
 */
class Multilang extends AbstractService
{
    /** @var array */
    public const LAYOUTS = ['detail', 'detailSmall'];
    /** @var array */
    public const SKIP_ENTITIES  = ['ProductAttributeValue'];

    /**
     * @return bool
     */
    public function updateLayouts(): bool
    {
        // exit is multi-lang inactive
        if (!$this->getConfig()->get('isMultilangActive', false)) {
            return false;
        }

        /** @var bool $isUpdated */
        $isUpdated = false;

        foreach ($this->getMetadata()->get(['entityDefs'], []) as $scope => $data) {
            if (!isset($data['fields']) || in_array($scope, self::SKIP_ENTITIES, true)) {
                continue 1;
            }
            $layoutFields = $this->getLayoutsFields($scope);
            $row = [];
            foreach ($data['fields'] as $field => $defs) {
                if (!empty($defs['multilangLocale']) && empty($defs['isCompleteness'])) {
                    foreach (self::LAYOUTS as $layoutName) {
                        if ($this->isNeedAddField($field, $layoutFields[$layoutName], $defs)) {
                            $row[$layoutName][] = ['name' => $field];
                            if (\count($row[$layoutName]) === 2) {
                                $this->updateLayout($scope, $layoutName, $row[$layoutName]);
                                $isUpdated = true;
                                $row[$layoutName] = [];
                            }
                        }
                    }
                }
            }
            foreach (self::LAYOUTS as $layoutName) {
                if (!empty($row[$layoutName])) {
                    $row[$layoutName][] = false;
                    $this->updateLayout($scope, $layoutName, $row[$layoutName]);
                    $isUpdated = true;
                    $row[$layoutName] = [];
                }
            }
        }

        if ($isUpdated) {
            $this->getLayout()->save();
        }

        return true;
    }

    protected function isNeedAddField(string $field, array $layoutFields, array $row): bool
    {
       return !in_array($field, $layoutFields, true)
         && in_array($row['multilangField'], $layoutFields, true);
    }

    /**
     * @param string $scope
     * @param string $layout
     * @param string $field
     *
     * @return bool
     */
    protected function updateLayout(string $scope, string $layout, array $row): bool
    {
        $data = Json::decode($this->getLayout()->get($scope, $layout), true);
        $data[0]['rows'][] = $row;
        $this->getLayout()->set($data, $scope, $layout);

        return true;
    }

    /**
     * @param $scope
     * @return array
     */
    protected function getLayoutsFields($scope): array
    {
        $layoutFields = [];
        foreach (self::LAYOUTS as $layoutName) {
            $layoutFields[$layoutName] = $this->getLayoutFields($scope, $layoutName);
        }

        return $layoutFields;
    }

    /**
     * @param string $scope
     * @param string $layout
     *
     * @return array
     */
    protected function getLayoutFields(string $scope, string $layout): array
    {
        $fields = [];
        foreach (Json::decode($this->getLayout()->get($scope, $layout), true) as $row) {
            foreach ($row['rows'] as $item) {
                foreach ($item as $v) {
                    if (isset($v['name'])) {
                        $fields[] = $v['name'];
                    }
                }
            }
        }

        return $fields;
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
