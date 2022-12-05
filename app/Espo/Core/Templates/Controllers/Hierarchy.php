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

declare(strict_types=1);

namespace Espo\Core\Templates\Controllers;

use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;

class Hierarchy extends Record
{
    public function actionTree($params, $data, $request): array
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $params = [
            'where'       => $this->prepareWhereQuery($request->get('where')),
            'asc'         => $request->get('asc', 'true') === 'true',
            'sortBy'      => $request->get('sortBy'),
            'isTreePanel' => !empty($request->get('isTreePanel')),
            'offset'      => (int)$request->get('offset'),
            'maxSize'     => (int)$request->get('maxSize')
        ];

        return $this->getRecordService()->getChildren((string)$request->get('node'), $params);
    }

    public function actionTreeDataForSelectedNode($params, $data, $request): array
    {
        if (!$request->isGet() || empty($request->get('id'))) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->getTreeDataForSelectedNode((string)$request->get('id'));
    }

    public function actionTreeData($params, $data, $request): array
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        if (!empty($request->get('ids'))) {
            $ids = (array)$request->get('ids');
        } elseif (!empty($request->get('where'))) {
            $params = [
                'select'  => ['id'],
                'where'   => $this->prepareWhereQuery($request->get('where')),
                'offset'  => 0,
                'maxSize' => 5000,
                'asc'     => true,
                'sortBy'  => 'id'
            ];

            $result = $this->getRecordService()->findEntities($params);
            if (!empty($result['total'])) {
                $ids = array_column($result['collection']->toArray(), 'id');
            }
        }

        if (empty($ids)) {
            return [
                'total' => 0,
                'tree'  => []
            ];
        }

        return $this->getRecordService()->getTreeData($ids);
    }

    public function actionRoute($params, $data, $request): array
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->getRoute((string)$request->get('id'));
    }

    public function actionInheritField($params, $data, $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'field') || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->inheritField((string)$data->field, (string)$data->id);
    }

    public function actionInheritAll($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'id') || !property_exists($data, 'link')) {
            throw new BadRequest();
        }

        return $this->getRecordService()->inheritAll((string)$data->id, (string)$data->link);
    }

    public function actionUnlinkAllHierarchically($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'id') || !property_exists($data, 'link')) {
            throw new BadRequest();
        }

        return $this->getRecordService()->unlinkAllHierarchically((string)$data->id, (string)$data->link);
    }
}
