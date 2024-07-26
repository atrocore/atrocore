<?php

namespace Atro\NotificationTransport;

use Atro\Core\Container;
use Atro\Entities\Connection;
use Atro\Entities\NotificationTemplate;
use Projects\Entities\User;

abstract class AbstractNotificationTransport
{
    protected Container $container;

    protected ?Connection $connectionEntity;

    protected User $user;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

     public  function setConnection(Connection $connectionEntity): self
    {
        $this->connectionEntity = $connectionEntity;
        return $this;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    abstract public function send(NotificationTemplate $template): void;

}