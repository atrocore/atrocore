<?php

namespace Espo\EntryPoints;

use Treo\Core\EntryPoints\AbstractEntryPoint;

class OauthCallback extends AbstractEntryPoint
{
    public static $authRequired = false;

    public function run()
    {
        echo "EspoCRM rocks !!!";
    }
}

