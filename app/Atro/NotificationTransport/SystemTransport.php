<?php

namespace Atro\NotificationTransport;

use Atro\Entities\NotificationTemplate;

class SystemTransport extends AbstractNotificationTransport
{

    public function send(NotificationTemplate $template): void
    {
    }
}