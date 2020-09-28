<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\Forbidden;

class Portal extends \Espo\Core\Controllers\Record
{
    protected function checkControllerAccess()
    {
        $portalPermission = $this->getAcl()->get('portalPermission');
        if (!$portalPermission || $portalPermission === 'no') {
            throw new Forbidden();
        }
    }
}
