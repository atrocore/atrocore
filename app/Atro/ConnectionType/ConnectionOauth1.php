<?php

namespace Atro\ConnectionType;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\Services\Connection;

class ConnectionOauth1 extends AbstractConnection
{

    public function connect(Entity $connection)
    {
        if (empty($connection->get('oauthToken')) || empty($connection->get('oauthTokenSecret')) || empty($connection->get('storeUrl'))) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), 'You should authorize this connection on the provider using the callback and link urls below'));
        }

        $url = $connection->get('apiTestUrl');

        /** @var Connection $connectionService */
        $connectionService = $this->container->get('serviceFactory')->create('Connection');
        $authorization = $connectionService->buildAuthorizationHeaderForAPIRequest($connection, 'GET', $url);

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
                'Authorization: ' . $authorization,
                'Accept: application/json',
            ],
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        echo $response;


    }
}