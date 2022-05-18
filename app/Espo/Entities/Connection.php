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

namespace Espo\Entities;

use Espo\Core\Templates\Entities\Base;
use Espo\Core\Utils\Json;

class Connection extends Base
{
    protected $entityType = "Connection";

    public function setDataField(string $name, $value): void
    {
        $data = [];
        if (!empty($this->get('data'))) {
            $data = Json::decode(Json::encode($this->get('data')), true);
        }

        $data[$name] = $value;

        $this->valuesContainer[$name] = $value;
        $this->set('data', $data);
    }

    public function getDataField(string $name)
    {
        $data = $this->getDataFields();

        if (!isset($data[$name])) {
            return null;
        }

        return $data[$name];
    }

    public function getDataFields(): array
    {
        if (!empty($data = $this->get('data'))) {
            $data = Json::decode(Json::encode($data), true);
            if (!empty($data) && is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    public function _setOauthGrantType($value)
    {
        $this->setDataField('oauthGrantType', $value);
    }

    public function _getOauthGrantType()
    {
        return $this->getDataField('oauthGrantType');
    }

    public function _setOauthClientId($value)
    {
        $this->setDataField('oauthClientId', $value);
    }

    public function _getOauthClientId()
    {
        return $this->getDataField('oauthClientId');
    }

    public function _setOauthClientSecret($value)
    {
        $this->setDataField('oauthClientSecret', $value);
    }

    public function _getOauthClientSecret()
    {
        return $this->getDataField('oauthClientSecret');
    }
}
