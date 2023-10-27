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
        /** @var Connection $connectionService */
        $connectionService = $this->container->get('serviceFactory')->create('Connection');
        $criteria = [
            'searchCriteria' => [
                'filterGroups' => [
                    [
                        'filters' => [
                            [
                                'field' => 'name',
                                'value' => '%a%',
                                'condition_type' => 'like'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $url = $connection->get('storeUrl') . "rest/V1/products/?".http_build_query($criteria);
        $data = $connectionService->apiRequest($connection, 'GET', $url);
        var_dump($data);

    }
}