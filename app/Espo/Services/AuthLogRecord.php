<?php

namespace Espo\Services;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\NotFound;

class AuthLogRecord extends Record
{
    protected $internalAttributeList = [];

    protected $actionHistoryDisabled = true;

    protected $forceSelectAllAttributes = true;

    protected $readOnlyAttributeList = [
        "username",
        "portalId",
        "userId",
        "authTokenId",
        "ipAddress",
        "isDenied",
        "denialReason",
        "microtime",
        "requestUrl",
        "requestMethod"
    ];
}
