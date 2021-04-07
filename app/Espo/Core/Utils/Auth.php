<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\Core\Utils;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;

use \Espo\Entities\Portal;

class Auth
{
    protected $container;

    protected $authentication;

    protected $allowAnyAccess;

    const FAILED_ATTEMPTS_PERIOD = '60 seconds';

    const MAX_FAILED_ATTEMPT_NUMBER = 10;

    private $portal;

    public function __construct(\Espo\Core\Container $container, $allowAnyAccess = false)
    {
        $this->container = $container;

        $this->allowAnyAccess = $allowAnyAccess;

        $authenticationMethod = $this->getConfig()->get('authenticationMethod', 'Basic');
        $authenticationClassName = "\\Espo\\Core\\Utils\\Authentication\\" . $authenticationMethod;
        $this->authentication = new $authenticationClassName($this->getConfig(), $this->getEntityManager(), $this);

        $this->request = $container->get('slim')->request();
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function setPortal(Portal $portal)
    {
        $this->portal = $portal;
    }

    protected function isPortal()
    {
        if ($this->portal) {
            return true;
        }
        return !!$this->getContainer()->get('portal');
    }

    protected function getPortal()
    {
        if ($this->portal) {
            return $this->portal;
        }
        return $this->getContainer()->get('portal');
    }

    protected function getConfig()
    {
        return $this->getContainer()->get('config');
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('entityManager');
    }

    public function useNoAuth()
    {
        if (!file_exists($this->getConfig()) || !$this->getConfig()->get('isInstalled', false)) {
            return;
        }

        $entityManager = $this->getContainer()->get('entityManager');

        $user = $entityManager->getRepository('User')->get('system');
        if (!$user) {
            throw new Error("System user is not found");
        }

        $user->set('isAdmin', true);
        $user->set('ipAddress', $_SERVER['REMOTE_ADDR']);

        $entityManager->setUser($user);
        $this->getContainer()->setUser($user);
    }

    public function login(string $token): bool
    {
        if (!$this->getConfig()->get('isModulesLoaded', true)) {
            throw new Error('Not all modules are loaded. Please try later.');
        }

        $authToken = $this->getEntityManager()->getRepository('AuthToken')->where(['token' => $token])->findOne();

        if ($authToken && $authToken->get('isActive')) {
            if (!$this->allowAnyAccess) {
                if ($this->isPortal() && $authToken->get('portalId') !== $this->getPortal()->id) {
                    $GLOBALS['log']->info("AUTH: Trying to login to portal with a token not related to portal.");
                    return false;
                }
                if (!$this->isPortal() && $authToken->get('portalId')) {
                    $GLOBALS['log']->info("AUTH: Trying to login to crm with a token related to portal.");
                    return false;
                }
            }
            if ($this->allowAnyAccess) {
                if ($authToken->get('portalId') && !$this->isPortal()) {
                    $portal = $this->getEntityManager()->getEntity('Portal', $authToken->get('portalId'));
                    if ($portal) {
                        $this->setPortal($portal);
                    }
                }
            }
        } else {
            $GLOBALS['log']->info("AUTH: Trying to login but token is not found.");
            return false;
        }

        if (empty($user = $this->authentication->login($authToken, $this->isPortal()))) {
            return false;
        }

        if (!$user) {
            return false;
        }

        if (!$user->isActive()) {
            $GLOBALS['log']->info("AUTH: Trying to login as user '" . $user->get('userName') . "' which is not active.");
            return false;
        }

        if (!$user->isAdmin() && !$this->isPortal() && $user->get('isPortalUser')) {
            $GLOBALS['log']->info("AUTH: Trying to login to system as a portal user '" . $user->get('userName') . "'.");
            return false;
        }

        if (!$user->isAdmin() && $this->isPortal() && !$user->get('isPortalUser')) {
            $GLOBALS['log']->info("AUTH: Trying to login to portal as user '" . $user->get('userName') . "' which is not portal user.");
            return false;
        }

        if ($this->isPortal()) {
            if (!$user->isAdmin() && !$this->getEntityManager()->getRepository('Portal')->isRelated($this->getPortal(), 'users', $user)) {
                $GLOBALS['log']->info("AUTH: Trying to login to portal as user '" . $user->get('userName') . "' which is portal user but does not belongs to portal.");
                return false;
            }
            $user->set('portalId', $this->getPortal()->id);
        } else {
            $user->loadLinkMultipleField('teams');
        }

        $user->set('ipAddress', $_SERVER['REMOTE_ADDR']);

        $this->getEntityManager()->setUser($user);
        $this->getContainer()->setUser($user);

//        if ($this->request->headers->get('HTTP_BASIC_AUTHORIZATION')) {
//            if (!$authToken) {
//                $authToken = $this->getEntityManager()->getEntity('AuthToken');
//                $token = $this->generateToken();
//                $authToken->set('token', $token);
//                $authToken->set('hash', $user->get('password'));
//                $authToken->set('ipAddress', $_SERVER['REMOTE_ADDR']);
//                $authToken->set('userId', $user->id);
//                if ($this->isPortal()) {
//                    $authToken->set('portalId', $this->getPortal()->id);
//                }
//
//                if ($this->getConfig()->get('authTokenPreventConcurrent')) {
//                    $concurrentAuthTokenList = $this->getEntityManager()->getRepository('AuthToken')->select(['id'])->where([
//                        'userId' => $user->id,
//                        'isActive' => true
//                    ])->find();
//                    foreach ($concurrentAuthTokenList as $concurrentAuthToken) {
//                        $concurrentAuthToken->set('isActive', false);
//                        $this->getEntityManager()->saveEntity($concurrentAuthToken);
//                    }
//                }
//            }
//        	$authToken->set('lastAccess', date('Y-m-d H:i:s'));
//
//        	$this->getEntityManager()->saveEntity($authToken);
//        	$user->set('token', $authToken->get('token'));
//            $user->set('authTokenId', $authToken->id);
//        }

        $authLogRecord = $this
            ->getEntityManager()
            ->getRepository('AuthLogRecord')
            ->select(['id'])
            ->where(['authTokenId' => $authToken->id])
            ->order('requestTime', true)
            ->findOne();

        if (!empty($authLogRecord)) {
            $user->set('authLogRecordId', $authLogRecord->id);
        }

        return true;
    }

    public function destroyAuthToken($token): bool
    {
        $authToken = $this->getEntityManager()->getRepository('AuthToken')->select(['id', 'isActive'])->where(['token' => $token])->findOne();
        if ($authToken) {
            $authToken->set('isActive', false);
            $this->getEntityManager()->saveEntity($authToken);
            return true;
        }

        return false;
    }

    protected function generateToken(): string
    {
        $length = 16;

        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length));
        }
        if (function_exists('mcrypt_create_iv')) {
            return bin2hex(mcrypt_create_iv($length, \MCRYPT_DEV_URANDOM));
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length));
        }

        return substr(md5(md5(time()) . time()), 0, $length);
    }
}
