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

declare(strict_types=1);

namespace Espo\Controllers;

use  Atro\Core\Exceptions\BadRequest;
use  Atro\Core\Exceptions\Forbidden;

class MassActions extends \Espo\Core\Controllers\Base
{
    public function actionUpsert($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        $useQueue = $request->headers('use-queue');
        $viaQm = $useQueue === '1' || strtolower((string)$useQueue) === 'true';

        $data = (array)$data;

        if ($viaQm) {
            return $this->getService('MassActions')->upsertViaQm($data);
        }

        return $this->getService('MassActions')->upsert($data);
    }

    public function actionAddRelation($params, $data, $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'where') || !property_exists($data ,'foreignWhere') || !isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        $where = json_decode(json_encode($data->where), true);
        $foreignWhere = json_decode(json_encode($data->foreignWhere), true);
        return $this->getService('MassActions')
            ->addRelationByWhere($where, $foreignWhere, $params['scope'], $params['link']);
    }

    public function actionRemoveRelation($params, $data, $request): array
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'where') || !property_exists($data ,'foreignWhere') || !isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        $where = json_decode(json_encode($data->where), true);
        $foreignWhere = json_decode(json_encode($data->foreignWhere), true);
        return $this->getService('MassActions')
            ->removeRelationByWhere($where, $foreignWhere, $params['scope'], $params['link']);
    }
}
