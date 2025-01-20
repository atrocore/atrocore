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

namespace Atro\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;

class Layout extends AbstractRecordController
{
    public function actionGetContent($params, $data, $request)
    {
        $data = $this->getContainer()->get('layout')->get($params['scope'], $params['name'],
            $request->get('relatedScope') ?? null, $request->get('layoutProfileId') ?? null,
            $request->get('isAdminPage') === 'true');
        if (empty($data)) {
            throw new NotFound("Layout " . $params['scope'] . ":" . $params['name'] . ' is not found.');
        }
        return $data;
    }

    public function actionUpdateContent($params, $data, $request)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        $layoutProfileId = (string)$request->get('layoutProfileId');
        $relatedEntity = (string)$request->get('relatedScope');

        if ((!$request->isPut() && !$request->isPatch()) || empty($layoutProfileId)) {
            throw new BadRequest();
        }

        /** @var \Atro\Core\Utils\Layout $layoutManager */
        $layoutManager = $this->getContainer()->get('layout');
        $layoutManager->checkLayoutProfile($layoutProfileId);
        $result = $layoutManager->save($params['scope'], $params['name'], $relatedEntity, $layoutProfileId, json_decode(json_encode($data), true));

        if ($result === false) {
            throw new Error("Error while saving layout.");
        }
        if ($layoutProfileId !== 'custom') {
            $this->getContainer()->get('dataManager')->clearCache();
        }

        return $layoutManager->get($params['scope'], $params['name'], $relatedEntity, $layoutProfileId);
    }

    public function actionResetToDefault($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($data->scope) || empty($data->name) || empty($data->layoutProfileId)) {
            throw new BadRequest();
        }

        /** @var \Atro\Core\Utils\Layout $layoutManager */
        $layoutManager = $this->getContainer()->get('layout');
        $layoutManager->checkLayoutProfile((string)$data->layoutProfileId);
        return $layoutManager->resetToDefault((string)$data->scope, (string)$data->name, (string)$data->relatedScope, (string)$data->layoutProfileId);
    }

    /**
     * @param mixed $params
     * @param mixed $data
     * @param mixed $request
     *
     * @return bool
     * @throws BadRequest
     */
    public function actionResetAllToDefault($params, $data, $request): bool
    {
        $layoutProfileId = (string)$request->get('layoutProfileId');

        if (!$request->isPost() || empty($layoutProfileId)) {
            throw new BadRequest();
        }

        /** @var \Atro\Core\Utils\Layout $layoutManager */
        $layoutManager = $this->getContainer()->get('layout');
        $layoutManager->checkLayoutProfile($layoutProfileId);
        return $layoutManager->resetAllToDefault($layoutProfileId);
    }
}
