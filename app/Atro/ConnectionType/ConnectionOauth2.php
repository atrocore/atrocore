<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\ConnectionType;

use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionOauth2 extends ConnectionHttp implements ConnectionInterface
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

    public function getHeaders(): array
    {
        $connectionData = $this->connect($this->connectionEntity);

        return ["Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"];
    }
}
