<?php

namespace Atro\ConnectionType;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ConnectionOauth1 extends AbstractConnection
{

    public function connect(Entity $connection)
    {
        if(empty($connection->get('oauthToken')) || empty($connection->get('oauthTokenSecret'))){
            throw new BadRequest(sprintf($this->exception('connectionFailed'), 'You should authorize this connection on the provider using the callback and link urls below'));
        }
    }
}