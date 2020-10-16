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

namespace Treo\Controllers;

use Espo\Core\Controllers\Base;
use Espo\Core\Exceptions;
use Espo\Core\Utils\Json;
use Slim\Http\Request;
use Treo\Services\Composer as ComposerService;

/**
 * Composer controller
 */
class Composer extends Base
{
    /**
     * @ApiDescription(description="Call composer update command")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/Composer/runUpdate")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
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

    /**
     * @ApiDescription(description="Cancel changes")
     * @ApiMethod(type="DELETE")
     * @ApiRoute(name="/Composer/cancelUpdate")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
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

    /**
     * @ApiDescription(description="Get modules")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/ModuleManager/list")
     * @ApiReturn(sample="{
     *     'total': 1,
     *     'list': [
     *           {
     *               'id': 'Revisions',
     *               'name': 'Revisions',
     *               'version': '1.0.0',
     *               'description': 'Module Revisions.',
     *               'required': [],
     *               'isActive': true
     *           }
     *       ]
     * }")
     *
     * @return array
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     */
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

    /**
     * @ApiDescription(description="Install module")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/ModuleManager/installModule")
     * @ApiBody(sample="{
     *     'id': 'Erp',
     *     'version': '1.0.0' - not required
     * }")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
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

    /**
     * @ApiDescription(description="Update module version")
     * @ApiMethod(type="PUT")
     * @ApiRoute(name="/ModuleManager/updateModule")
     * @ApiBody(sample="{
     *     'id': 'Erp',
     *     'version': '1.1.0'
     * }")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
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

        if (!empty($data['id']) && !empty($data['version'])) {
            return $this->getComposerService()->updateModule($data['id'], $data['version']);
        }

        throw new Exceptions\NotFound();
    }

    /**
     * @ApiDescription(description="Delete module")
     * @ApiMethod(type="DELETE")
     * @ApiRoute(name="/ModuleManager/deleteModule")
     * @ApiBody(sample="{
     *     'id': 'Erp'
     * }")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
    public function actionDeleteModule($params, $data, Request $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Exceptions\Forbidden();
        }

        if (!$request->isDelete()) {
            throw new Exceptions\BadRequest();
        }

        // prepare data
        $data = Json::decode(Json::encode($data), true);

        if (!empty($id = $data['id'])) {
            return $this->getComposerService()->deleteModule($id);
        }

        throw new Exceptions\NotFound();
    }

    /**
     * @ApiDescription(description="Cancel module changes")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/ModuleManager/cancel")
     * @ApiBody(sample="{
     *     'id': 'Erp'
     * }")
     * @ApiReturn(sample="'bool'")
     *
     * @return bool
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     * @throws Exceptions\NotFound
     */
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

    /**
     * @ApiDescription(description="Get composer stream data")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/ModuleManager/logs")
     * @ApiReturn(sample="{
     *     'total': 'int',
     *     'list': 'array'
     * }")
     *
     * @return array
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     */
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
