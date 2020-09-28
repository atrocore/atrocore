<?php

namespace Espo\Services;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\NotFound;

class AuthToken extends Record
{
    protected $internalAttributeList = ['hash', 'token'];

    protected $actionHistoryDisabled = true;

    protected $readOnlyAttributeList = [
        'token',
        'hash',
        'userId',
        'portalId',
        'ipAddress',
        'lastAccess',
        'createdAt',
        'modifiedAt'
    ];
}

