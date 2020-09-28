<?php

namespace Espo\Jobs;

use \Espo\Core\Exceptions;

class SendEmailNotifications extends \Espo\Core\Jobs\Base
{
    public function run()
    {
        $service = $this->getServiceFactory()->create('EmailNotification');
        $service->process();
    }
}

