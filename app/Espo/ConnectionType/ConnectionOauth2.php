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

namespace Espo\ConnectionType;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionOauth2 extends AbstractConnection
{
    public function connect(Entity $connection)
    {
        $body = ['grant_type' => $connection->get('oauthGrantType')];

        switch ($body['grant_type']) {
            case 'client_credentials':
                $body['client_id'] = $connection->get('oauthClientId');
                $body['client_secret'] = $this->decryptPassword($connection->get('oauthClientSecret'));
                break;
            default:
                throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $connection->get('oauthUrl'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!empty($response)) {
            $result = @json_decode($response, true);
            if (isset($result['access_token'])) {
                return $result;
            }
        }

        throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
    }
}
