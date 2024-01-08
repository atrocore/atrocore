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

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;

class Settings extends \Espo\Core\Controllers\Base
{
    protected function getConfigData()
    {
        if ($this->getUser()->id == 'system') {
            $data = $this->getConfig()->getData();
        } else {
            $data = $this->getConfig()->getData($this->getUser()->isAdmin());
        }

        $fieldDefs = $this->getMetadata()->get('entityDefs.Settings.fields');

        foreach ($fieldDefs as $field => $d) {
            if ($d['type'] === 'password') {
                unset($data[$field]);
            }
        }

        $data['jsLibs'] = $this->getMetadata()->get('app.jsLibs');
        $data['themes'] = $this->getMetadata()->get('themes');
        $data['coreVersion'] = \Espo\Services\Composer::getCoreVersion();

        $data = $this->prepareTabList($data);

        return $data;
    }

    public function actionRead($params, $data)
    {
        return $this->getConfigData();
    }

    public function actionUpdate($params, $data, $request)
    {
        return $this->actionPatch($params, $data, $request);
    }

    public function actionPatch($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        if (!$request->isPut() && !$request->isPatch()) {
            throw new BadRequest();
        }

        $this->getServiceFactory()->create('Settings')->validate($data);

        // clear cache
        $this->getContainer()->get('dataManager')->clearCache();

        if (property_exists($data, 'siteUrl')) {
            $data->siteUrl = rtrim($data->siteUrl, '/');
        }

        $this->getConfig()->setData($data, $this->getUser()->isAdmin());
        $result = $this->getConfig()->save();
        if ($result === false) {
            throw new Error('Cannot save settings');
        }

        if (isset($data->inputLanguageList)) {
            $this->getContainer()->get('dataManager')->rebuild();
        }

        return $this->getConfigData();
    }

    protected function prepareTabList(array $config): array
    {
        if (!empty($config['tabList'])) {
            $newTabList = [];
            foreach ($config['tabList'] as $item) {
                if (is_string($item) && ($this->getMetadata()->get("scopes.$item.tab") || $item == '_delimiter_')) {
                    $newTabList[] = $item;
                }
            }
            $config['tabList'] = $newTabList;
        }

        return $config;
    }
}
