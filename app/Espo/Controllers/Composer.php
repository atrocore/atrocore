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

use Espo\Core\Controllers\Base;
use Espo\Core\Exceptions;
use Espo\Core\Utils\Json;
use Espo\Services\Composer as ComposerService;
use Slim\Http\Request;

class Composer extends Base
{
    public function actionRunUpdate($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        if (file_exists(ComposerService::CHECK_UP_FILE)) {
            throw new Exceptions\BadRequest('Composer daemon is not running');
        }

        return $this->getComposerService()->runUpdate();
    }

    public function actionCancelUpdate($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isDelete()) {
            throw new Exceptions\BadRequest();
        }

        // cancel changes
        $this->getComposerService()->cancelChanges();

        return true;
    }

    public function actionList($params, $data, Request $request): array
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        return $this->getComposerService()->getList();
    }

    public function actionInstallModule($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        // prepare data
        $data = Json::decode(Json::encode($data), true);

        if (!empty($data['id'])) {
            // prepare version
            $version = (!empty($data['version'])) ? $data['version'] : null;

            return $this->getComposerService()->installModule($data['id'], $version);
        }

        throw new Exceptions\NotFound();
    }

    public function actionUpdateModule($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPut()) {
            throw new Exceptions\BadRequest();
        }

        // prepare data
        $data = Json::decode(Json::encode($data), true);

        if (!empty($data['id'])) {
            // prepare version
            $version = (!empty($data['version'])) ? $data['version'] : null;

            return $this->getComposerService()->updateModule($data['id'], $version);
        }

        throw new Exceptions\NotFound();
    }

    public function actionDeleteModule($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isDelete()) {
            throw new Exceptions\BadRequest();
        }

        if (!empty($data)) {
            $data = Json::decode(Json::encode($data), true);
            if (!empty($data['id'])) {
                $id = $data['id'];
            }
        }

        if (!empty($request->get('id'))) {
            $id = $request->get('id');
        }

        if (!empty($id)) {
            return $this->getComposerService()->deleteModule($id);
        }

        throw new Exceptions\NotFound();
    }

    public function actionCancel($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        // prepare data
        $data = Json::decode(Json::encode($data), true);

        if (!empty($id = $data['id'])) {
            return $this->getComposerService()->cancel($id);
        }

        throw new Exceptions\NotFound();
    }

    public function actionLogs($params, $data, Request $request): array
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        return $this->getComposerService()->getLogs($request);
    }


    /**
     * @param mixed   $params
     * @param mixed   $data
     * @param Request $request
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionCheck($params, $data, Request $request): array
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        return $this->getComposerService()->checkUpdate();
    }

    /**
     * @return ComposerService
     */
    protected function getComposerService(): ComposerService
    {
        return $this->getService('Composer');
    }
}
