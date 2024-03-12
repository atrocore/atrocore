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

namespace Espo\Controllers;

use Atro\Core\QueueManager;
use Espo\Core\Controllers\Base;
use Espo\Core\DataManager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Auth;

/**
 * Class App
 */
class App extends Base
{
    /**
     * @param mixed $params
     * @param mixed $data
     * @param mixed $request
     *
     * @return array
     */
    public function actionBackground($params, $data, $request)
    {
        \Atro\EntryPoints\Background::setBackground();

        return $_SESSION['background'];
    }

    /**
     * @param mixed $params
     * @param mixed $data
     * @param mixed $request
     *
     * @return array
     */
    public function actionUser($params, $data, $request)
    {
        $data = $this->getService('App')->getUserData();
        $data['authorizationToken'] = base64_encode("{$data['user']->userName}:{$data['user']->token}");

        $tokenOnly = $request->headers('Authorization-Token-Only');
        if ($tokenOnly === 'true' || $tokenOnly === '1') {
            return ['authorizationToken' => $data['authorizationToken']];
        }

        return $data;
    }

    /**
     * @param mixed $params
     * @param mixed $data
     * @param mixed $request
     *
     * @return bool
     * @throws BadRequest
     */
    public function postActionDestroyAuthToken($params, $data, $request)
    {
        if (!property_exists($data, 'token')) {
            throw new BadRequest();
        }

        return (new Auth($this->getContainer()))->destroyAuthToken($data->token);
    }

    public function postActionUpdatePublicDataKey($params, $data, $request): bool
    {
        if (!property_exists($data, 'key') || !property_exists($data, 'value') || in_array($data->key, ['dataTimestamp', 'notReadCount'])) {
            return false;
        }

        DataManager::pushPublicData($data->key, $data->value);

        return true;
    }

    public function postActionQueueManagerUpdate($params, $data, $request): bool
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        if (!property_exists($data, 'pause')) {
            return false;
        }

        if (!empty($data->pause)) {
            file_put_contents(QueueManager::PAUSE_FILE, '1');
        } else {
            if (file_exists(QueueManager::PAUSE_FILE)) {
                unlink(QueueManager::PAUSE_FILE);
            }
        }

        DataManager::pushPublicData('qmPaused', file_exists(QueueManager::PAUSE_FILE));

        return true;
    }

    /**
     * @param mixed $params
     * @param mixed $data
     * @param mixed $request
     *
     * @return mixed
     * @throws Forbidden
     */
    public function actionSendTestEmail($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return $this->getService('App')->sendTestEmail(get_object_vars($data));
    }
}
