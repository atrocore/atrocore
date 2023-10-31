<?php

namespace Atro\ConnectionType;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\Services\Connection;

class ConnectionOauth1 extends AbstractConnection
{

    public function connect(Entity $connection, $httpUrl = null)
    {
        if (empty($connection->get('oauthToken')) || empty($connection->get('oauthTokenSecret'))) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), 'You should authorize this connection on the provider using the callback and link urls below'));
        }

        /** @var Connection $connectionService */
        $connectionService = $this->container->get('serviceFactory')->create('Connection');

        if($httpUrl === null ){
            $httpUrl = $connection->get('apiTestUrl');
            $authorization = $connectionService->buildAuthorizationHeaderForAPIRequest($connection, 'GET', $httpUrl);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Oauth ' . $authorization,
                    'Accept: application/json',
                ],
            ));

            $response = curl_exec($curl);
            $curlInfo = curl_getinfo($curl);
            curl_close($curl);
            if ($curlInfo['http_code'] !== 200) {
                throw new BadRequest($response . " ApiStatusCode: " . $curlInfo['http_code']);
            }
        }else{
            $authorization = $connectionService->buildAuthorizationHeaderForAPIRequest($connection, 'GET', $httpUrl);

            return [
                "token_type" => "Oauth",
                "access_token" => $authorization
            ];
        }

    }
}