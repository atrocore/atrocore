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

namespace Treo\Controllers;

use Espo\Core\Controllers\Base;
use Slim\Http\Request;
use Espo\Core\Exceptions;
use Treo\Services\Installer as InstallerService;

/**
 * Class Installer
 *
 * @author r.ratsun <rr@atrocore.com>
 */
class Installer extends Base
{


    /**
     * @ApiDescription(description="Get translations")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/Installer/getTranslations")
     * @ApiReturn(sample="{
     *      'labels': {
     *          'key': 'string',
     *          ...
     *     },
     *     ...
     * }")
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionGetTranslations($params, $data, Request $request): array
    {
        // check method
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        return $installer->getTranslations();
    }


    /**
     * @ApiDescription(description="Set language")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/Installer/setLanguage")
     * @ApiBody(sample="{
     *     'language': 'en_US',
     * }")
     *
     * @ApiReturn(sample="{
     *      'status': bool,
     *      'message': string
     * }")
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionSetLanguage($params, $data, Request $request): array
    {
        // check method
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        $post = get_object_vars($data);

        // check if input params exists
        if (!isset($post['language'])) {
            throw new Exceptions\BadRequest();
        }

        return $installer->setLanguage($post['language']);
    }

    /**
     * @ApiDescription(description="Get default db settings")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/Installer/getDefaultDbSettings")
     * @ApiReturn(sample="{
     *      'driver':   'string'
     *      'host':     'string'
     *      'port':     'string'
     *      'charset':  'string'
     *      'dbname':   'string'
     *      'password': 'string'
     * }")
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionGetDefaultDbSettings($params, $data, Request $request): array
    {
        // check method
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        return $installer->getDefaultDbSettings();
    }

    /**
     * @ApiDescription(description="Set db settings")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/Installer/setDbSettings")
     * @ApiBody(sample="{
     *     'host':     'string',
     *     'dbname':   'string',
     *     'user':     'string',
     *     'password': 'string',
     *     'port':     'string'
     * }")
     *
     * @ApiReturn(sample="{
     *      'status': bool,
     *      'message': string
     * }")
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionSetDbSettings($params, $data, Request $request): array
    {
        // check method
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        $post = get_object_vars($data);

        // check if input params exists
        if (!$this->isValidDbParams($post)) {
            throw new Exceptions\BadRequest();
        }

        return $installer->setDbSettings($post);
    }

    /**
     * @ApiDescription(description="Create administrator")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/Installer/createAdmin")
     * @ApiBody(sample="{
     *     'username':        'string',
     *     'password':        'string',
     *     'confirmPassword': 'string',
     * }")
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionCreateAdmin($params, $data, Request $request): array
    {
        // check method
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        $post = get_object_vars($data);

        // check if input params exists
        if (empty($post['username']) || empty($post['password']) || empty($post['confirmPassword'])) {
            throw new Exceptions\BadRequest();
        }

        return $installer->createAdmin($post);
    }

    /**
     * @ApiDescription(description="Check data base connect")
     * @ApiMethod(type="POST")
     * @ApiRoute(name="/Installer/checkDbConnect")
     * @ApiBody(sample="{
     *     'host':     'string',
     *     'dbname':   'string',
     *     'user':     'string',
     *     'password': 'string',
     *     'port':     'string'
     * }")
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionCheckDbConnect($params, $data, Request $request): array
    {
        // check method
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        $post = get_object_vars($data);

        // check if input params exists
        if (!$this->isValidDbParams($post)) {
            throw new Exceptions\BadRequest();
        }

        return $installer->checkDbConnect($post);
    }

    /**
     * @ApiDescription(description="Get license and languages")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/Installer/getLicenseAndLanguages")
     * @ApiReturn(sample="{
     *      'languageList': 'array'
     *      'language':     'string'
     *      'license':      'string'
     * }")
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionGetLicenseAndLanguages($params, $data, Request $request): array
    {
        // check method
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        return $installer->getLicenseAndLanguages();
    }

    /**
     * @ApiDescription(description="Get requireds list")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/Installer/getRequiredsList")
     * @ApiReturn(sample="'array'")
     *
     * @return array
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionGetRequiredsList($params, $data, Request $request): array
    {
        // check method
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        return $installer->getRequiredsList();
    }

    /**
     * Check if valid db params
     *
     * @param array $post
     *
     * @return bool
     */
    protected function isValidDbParams(array $post): bool
    {
        return isset($post['host']) && isset($post['dbname']) && isset($post['user']);
    }
}
