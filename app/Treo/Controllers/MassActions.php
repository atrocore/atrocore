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

namespace Treo\Controllers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Slim\Http\Request;
use Treo\Core\EventManager\Event;

/**
 * Class MassActions
 */
class MassActions extends \Espo\Core\Controllers\Base
{
    public function actionMassDelete(array $params, \stdClass $data, Request $request): bool
    {
        if (!$request->isPost() || !isset($params['scope'])) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check($params['scope'], 'delete')) {
            throw new Forbidden();
        }

        $event = new Event(['params' => $params, 'data' => $data, 'request' => $request]);
        $this
            ->getContainer()
            ->get('eventManager')
            ->dispatch($params['scope'] . 'Controller', 'beforeActionMassDelete', $event);

        return $this->getService('MassActions')->massDelete($params['scope'], $data);
    }

    public function actionAddRelation($params, $data, $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!empty($data->ids)) {
            $ids = $data->ids;
        }

        if (!empty($request->get('ids'))) {
            $ids = explode(',', $request->get('ids'));
        }

        if (!empty($data->foreignIds)) {
            $foreignIds = $data->foreignIds;
        }

        if (!empty($request->get('foreignIds'))) {
            $foreignIds = explode(',', $request->get('foreignIds'));
        }

        if (empty($ids) || empty($foreignIds) || !isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        return $this
            ->getService('MassActions')
            ->addRelation($ids, $foreignIds, $params['scope'], $params['link']);
    }

    public function actionRemoveRelation($params, $data, $request): array
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        if (!empty($data->ids)) {
            $ids = $data->ids;
        }

        if (!empty($request->get('ids'))) {
            $ids = explode(',', $request->get('ids'));
        }

        if (!empty($data->foreignIds)) {
            $foreignIds = $data->foreignIds;
        }

        if (!empty($request->get('foreignIds'))) {
            $foreignIds = explode(',', $request->get('foreignIds'));
        }

        if (empty($ids) || empty($foreignIds) || !isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        return $this
            ->getService('MassActions')
            ->removeRelation($ids, $foreignIds, $params['scope'], $params['link']);
    }
}
