<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\ConnectionType;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionOtto extends AbstractConnection implements ConnectionInterface
{
    public function connect(Entity $connection)
    {
        $providerAccessToken = $this->getProviderAccessToken($connection);

        $scopes = urldecode(join(" ",$connection->get('vendorOauthScopes')));
        if($providerAccessToken !== null){
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $connection->get('vendorOauthUrl'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => "scope=$scopes",
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                    "Authorization: Bearer {$providerAccessToken}"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            if (!empty($response)) {
                $result = @json_decode($response, true);
                if (isset($result['access_token'])) {
                    return [
                        "access_token" => $result['access_token'],
                        "token_type" => "Bearer"
                    ];
                }
            }
        }

        throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
    }


    private function getProviderAccessToken(Entity  $connection) :?string{
        $authClientSecret = $this->decryptPassword($connection->get('oauthClientSecret'));
        $ch = curl_init();
        $scope = urldecode($connection->get('oauthScope'));
        curl_setopt_array($ch, array(
            CURLOPT_URL =>  $connection->get('oauthUrl'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "client_id={$connection->get('oauthClientId')}&client_secret=$authClientSecret&grant_type={$connection->get('oauthGrantType')}&scope=$scope",
            CURLOPT_HTTPHEADER => array(
                'Cache-Control: no-cache',
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($ch);

        curl_close($ch);
        if (!empty($response)) {
            $result = @json_decode($response, true);
            if (isset($result['access_token'])) {

                return $result['access_token'];
            }
        }
        return null;
    }

    public function getHeaders(array $connectionData): array
    {
        return ["Authorization: {$connectionData['token_type']} {$connectionData['access_token']}"];
    }
}
