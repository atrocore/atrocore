<?php

namespace Atro\NotificationTransport;

use Atro\ConnectionType\ConnectionSmtp;
use Atro\Core\Container;
use Atro\Core\Mail\Sender;
use Atro\Entities\Connection;
use Atro\Entities\NotificationTemplate;

class EmailTransport extends AbstractNotificationTransport
{
    protected Sender $sender;
    public function __construct(Container $container, Sender $sender)
    {
        $this->sender = $sender;
        parent::__construct($container);
    }

    public function send(NotificationTemplate $template): void
    {
    }
}