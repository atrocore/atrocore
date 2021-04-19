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

use Espo\Core\Container;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Authentication\AbstractAuthentication;
use Espo\Entities\AuthLogRecord;
use Espo\Entities\Portal;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Treo\Core\Slim\Http\Request;

/**
 * Class Auth
 */
class Auth
{
    public const FAILED_ATTEMPTS_PERIOD = '60 seconds';

    public const MAX_FAILED_ATTEMPT_NUMBER = 10;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var AbstractAuthentication
     */
    private $authentication;

    /**
     * @var bool
     */
    private $allowAnyAccess;

    /**
     * @var Portal|null
     */
    private $portal;

    /**
     * @var Request
     */
    private $request;

    /**
     * Auth constructor.
     *
     * @param Container $container
     * @param bool      $allowAnyAccess
     */
    public function __construct(Container $container, bool $allowAnyAccess = false)
    {
        $this->container = $container;
        $this->allowAnyAccess = $allowAnyAccess;

        /** @var string $authenticationClassName */
        $authenticationClassName = $this->getMetadata()->get(['app', 'authentication', $this->getConfig()->get('authenticationMethod', 'Token')]);
        if (!is_a($authenticationClassName, AbstractAuthentication::class, true)) {
            $authenticationClassName = $this->getMetadata()->get(['app', 'authentication', 'Token']);
        }

        $this->authentication = new $authenticationClassName($this, $container);
        $this->request = $this->container->get('slim')->request();
    }

    public function useNoAuth(): void
    {
        if (!file_exists($this->getConfig()->getConfigPath()) || !$this->getConfig()->get('isInstalled', false)) {
            return;
        }

        $user = $this->getEntityManager()->getRepository('User')->get('system');
        $user->set('isAdmin', true);
        $user->set('ipAddress', $_SERVER['REMOTE_ADDR']);

        $this->getEntityManager()->setUser($user);
        $this->container->setUser($user);
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return bool
     * @throws Error
     * @throws Forbidden
     */
    public function login(string $username, string $password): bool
    {
        if (!$this->getConfig()->get('isModulesLoaded', true)) {
            throw new Error('Not all modules are loaded. Please try later.');
        }

        $isByTokenOnly = $this->request->getResourceUri() !== '/api/v1/App/user' && !preg_match('/^\/api\/v1\/portal-access\/(.*)\/App\/user$/', $this->request->getResourceUri());

        if (!$isByTokenOnly) {
            $this->checkFailedAttemptsLimit();
        }

        $authToken = $this
            ->getEntityManager()
            ->getRepository('AuthToken')
            ->where(['token' => $password, 'isActive' => true])
            ->findOne();

        if (!empty($authToken)) {
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
                        $this->portal = $portal;
                    }
                }
            }
        }

        if ($isByTokenOnly && empty($authToken)) {
            $GLOBALS['log']->info("AUTH: Trying to login as user '{$username}' by token but token is not found.");
            return false;
        }

        $user = $this->authentication->login($username, $password, $authToken, $this->isPortal());

        $authLogRecord = empty($authToken) ? $this->createAuthLogRecord($username, $user) : null;

        if (!$user) {
            return false;
        }

        if (!$user->isActive()) {
            $GLOBALS['log']->info("AUTH: Trying to login as user '" . $user->get('userName') . "' which is not active.");
            $this->logDenied($authLogRecord, 'INACTIVE_USER');
            return false;
        }

        if (!$user->isAdmin() && !$this->isPortal() && $user->get('isPortalUser')) {
            $GLOBALS['log']->info("AUTH: Trying to login to crm as a portal user '" . $user->get('userName') . "'.");
            $this->logDenied($authLogRecord, 'IS_PORTAL_USER');
            return false;
        }

        if (!$user->isAdmin() && $this->isPortal() && !$user->get('isPortalUser')) {
            $GLOBALS['log']->info("AUTH: Trying to login to portal as user '" . $user->get('userName') . "' which is not portal user.");
            $this->logDenied($authLogRecord, 'IS_NOT_PORTAL_USER');
            return false;
        }

        if ($this->isPortal()) {
            if (!$user->isAdmin() && !$this->getEntityManager()->getRepository('Portal')->isRelated($this->getPortal(), 'users', $user)) {
                $GLOBALS['log']->info("AUTH: Trying to login to portal as user '" . $user->get('userName') . "' which is portal user but does not belongs to portal.");
                $this->logDenied($authLogRecord, 'USER_IS_NOT_IN_PORTAL');
                return false;
            }
            $user->set('portalId', $this->getPortal()->id);
        } else {
            $user->loadLinkMultipleField('teams');
        }

        $user->set('ipAddress', $_SERVER['REMOTE_ADDR']);

        $this->getEntityManager()->setUser($user);
        $this->container->setUser($user);

        if (empty($authToken)) {
            $this->preventConcurrent($user->id);

            $authToken = $this->getEntityManager()->getEntity('AuthToken');
            $authToken->set('token', $this->generateToken());
            $authToken->set('hash', $user->get('password'));
            $authToken->set('ipAddress', $_SERVER['REMOTE_ADDR']);
            $authToken->set('userId', $user->id);
            if ($this->isPortal()) {
                $authToken->set('portalId', $this->getPortal()->id);
            }
        }
        $authToken->set('lastAccess', date('Y-m-d H:i:s'));
        $this->getEntityManager()->saveEntity($authToken);

        $user->set('token', $authToken->get('token'));
        $user->set('authTokenId', $authToken->id);

        if ($authLogRecord) {
            $authLogRecord->set('authTokenId', $authToken->id);
            $this->getEntityManager()->saveEntity($authLogRecord);
        }

        if ($authToken && !$authLogRecord) {
            $authLogRecord = $this
                ->getEntityManager()
                ->getRepository('AuthLogRecord')
                ->select(['id'])
                ->where(['authTokenId' => $authToken->id])
                ->order('requestTime', true)
                ->findOne();
        }

        if ($authLogRecord) {
            $user->set('authLogRecordId', $authLogRecord->id);
        }

        return true;
    }

    public function destroyAuthToken(string $token): bool
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

    protected function preventConcurrent(string $userId): void
    {
        if ($this->getConfig()->get('authTokenPreventConcurrent')) {
            $concurrentAuthTokenList = $this
                ->getEntityManager()
                ->getRepository('AuthToken')
                ->select(['id'])
                ->where(
                    [
                        'userId'   => $userId,
                        'isActive' => true
                    ]
                )
                ->find();
            foreach ($concurrentAuthTokenList as $concurrentAuthToken) {
                $concurrentAuthToken->set('isActive', false);
                $this->getEntityManager()->saveEntity($concurrentAuthToken);
            }
        }
    }

    protected function checkFailedAttemptsLimit(): void
    {
        $failedAttemptsPeriod = $this->getConfig()->get('authFailedAttemptsPeriod', self::FAILED_ATTEMPTS_PERIOD);
        $maxFailedAttempts = $this->getConfig()->get('authMaxFailedAttemptNumber', self::MAX_FAILED_ATTEMPT_NUMBER);

        $requestTimeFrom = (new \DateTime('@' . intval($_SERVER['REQUEST_TIME_FLOAT'])))->modify('-' . $failedAttemptsPeriod);

        $failAttemptCount = $this->getEntityManager()->getRepository('AuthLogRecord')->where(
            [
                'requestTime>' => $requestTimeFrom->format('U'),
                'ipAddress'    => $_SERVER['REMOTE_ADDR'],
                'isDenied'     => true
            ]
        )->count();

        if ($failAttemptCount > $maxFailedAttempts) {
            $GLOBALS['log']->warning("AUTH: Max failed login attempts exceeded for IP '" . $_SERVER['REMOTE_ADDR'] . "'.");
            throw new Forbidden("Max failed login attempts exceeded.");
        }
    }

    protected function createAuthLogRecord(string $username, ?User $user): ?AuthLogRecord
    {
        if ($username === '**logout') {
            return null;
        }

        $authLogRecord = $this->getEntityManager()->getEntity('AuthLogRecord');

        $authLogRecord->set(
            [
                'username'      => $username,
                'ipAddress'     => $_SERVER['REMOTE_ADDR'],
                'requestTime'   => $_SERVER['REQUEST_TIME_FLOAT'],
                'requestMethod' => $this->request->getMethod(),
                'requestUrl'    => $this->request->getUrl() . $this->request->getPath()
            ]
        );

        if ($this->isPortal()) {
            $authLogRecord->set('portalId', $this->getPortal()->id);
        }

        if ($user) {
            $authLogRecord->set('userId', $user->id);
        } else {
            $authLogRecord->set('isDenied', true);
            $authLogRecord->set('denialReason', 'CREDENTIALS');
            $this->getEntityManager()->saveEntity($authLogRecord);
        }

        return $authLogRecord;
    }

    protected function logDenied(?AuthLogRecord $authLogRecord, string $denialReason): void
    {
        if (!$authLogRecord) {
            return;
        }

        $authLogRecord->set('denialReason', $denialReason);
        $this->getEntityManager()->saveEntity($authLogRecord);
    }

    protected function isPortal(): bool
    {
        if ($this->portal) {
            return true;
        }
        return !!$this->container->get('portal');
    }

    protected function getPortal(): Portal
    {
        if ($this->portal) {
            return $this->portal;
        }

        return $this->container->get('portal');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }
}
