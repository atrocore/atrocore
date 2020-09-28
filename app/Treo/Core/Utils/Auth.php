<?php

declare(strict_types=1);

namespace Treo\Core\Utils;

use Espo\Core\Utils\Auth as EspoAuth;
use Espo\Core\Exceptions\Error;

/**
 * Class Auth
 *
 * @author y.haiduchyk <y.haiduchyk@zinitsolutions.com>
 */
class Auth extends EspoAuth
{
    /**
     * Disable auth
     *
     * @throws Error
     */
    public function useNoAuth()
    {
        // disable connect to DB if system not installed
        if ($this->getContainer()->get('serviceFactory')->create('Installer')->isInstalled()) {
            parent::useNoAuth();
        }
    }
}
