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

namespace Espo\Controllers;

use Espo\Core\Controllers\Base;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Util;

class Variable extends Base
{
    public function actionList($params, $data, $request)
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        $variables = $this->getConfig()->get('variables', []);

        return [
            'total' => count($variables),
            'list'  => $variables
        ];
    }

    public function actionCreate($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $variables = $this->getConfig()->get('variables', []);

        if ($data->name === 'variables' || in_array($data->name, array_column($variables, 'name')) || $this->getConfig()->has($data->name)) {
            throw new BadRequest("Such name '{$data->name}' is already using.");
        }

        $variables[] = [
            'id'    => Util::generateId(),
            'name'  => $data->name,
            'type'  => $data->type,
            'value' => $data->value,
        ];

        $this->getConfig()->set('variables', $variables);

        return $this->getConfig()->save();
    }

    public function actionDelete($params, $data, $request)
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $variables = [];
        foreach ($this->getConfig()->get('variables', []) as $row) {
            if ($row['id'] !== $params['id']) {
                $variables[] = $row;
            }
        }
        $this->getConfig()->set('variables', $variables);

        return $this->getConfig()->save();
    }
}
