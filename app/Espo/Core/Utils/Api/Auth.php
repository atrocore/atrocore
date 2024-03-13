<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

namespace Espo\Core\Utils\Api;

use Espo\Core\Utils\Auth as UtilsAuth;
use Slim\Middleware;

/**
 * Class Auth
 */
class Auth extends Middleware
{
    /**
     * @var UtilsAuth
     */
    protected $auth;

    /**
     * @var bool|null
     */
    protected $authRequired;

    /**
     * @var bool
     */
    protected $showDialog;

    /**
     * Auth constructor.
     *
     * @param UtilsAuth $auth
     * @param bool|null $authRequired
     * @param bool      $showDialog
     */
    public function __construct(UtilsAuth $auth, bool $authRequired = null, bool $showDialog = false)
    {
        $this->auth = $auth;
        $this->authRequired = $authRequired;
        $this->showDialog = $showDialog;
    }

    /**
     * @inheritDoc
     */
    public function call()
    {
        $req = $this->app->request();

        $uri = $req->getResourceUri();
        $httpMethod = $req->getMethod();

        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            list($authUsername, $authPassword) = explode(':', base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6)));
        } else {
            $authUsername = $req->headers('PHP_AUTH_USER');
            $authPassword = $req->headers('PHP_AUTH_PW');
        }

        $authToken = $req->headers('HTTP_AUTHORIZATION_TOKEN');
        if (isset($authToken)) {
            list($authUsername, $authPassword) = explode(':', base64_decode($authToken), 2);
        }

        if (!isset($authUsername) && !empty($_COOKIE['auth-username']) && !empty($_COOKIE['auth-token'])) {
            $authUsername = $_COOKIE['auth-username'];
            $authPassword = $_COOKIE['auth-token'];
        }

        if (is_null($this->authRequired)) {
            $routes = $this->app->router()->getMatchedRoutes($httpMethod, $uri);

            if (!empty($routes[0])) {
                $routeConditions = $routes[0]->getConditions();
                if (isset($routeConditions['auth']) && $routeConditions['auth'] === false) {

                    if ($authUsername && $authPassword) {
                        try {
                            $isAuthenticated = $this->auth->login($authUsername, $authPassword);
                        } catch (\Exception $e) {
                            $this->processException($e);
                            return;
                        }
                        if ($isAuthenticated) {
                            $this->next->call();
                            return;
                        }
                    }

                    $this->auth->useNoAuth();
                    $this->next->call();
                    return;
                }
            }
        } else {
            if (!$this->authRequired) {
                $this->auth->useNoAuth();
                $this->next->call();
                return;
            }
        }

        if ($authUsername && $authPassword) {
            try {
                $isAuthenticated = $this->auth->login($authUsername, $authPassword);
            } catch (\Exception $e) {
                $this->processException($e);
                return;
            }

            if ($isAuthenticated) {
                $this->next->call();
            } else {
                $this->processUnauthorized();
            }
        } else {
            if (!$this->isXMLHttpRequest()) {
                $this->showDialog = true;
            }
            $this->processUnauthorized();
        }
    }

    protected function processException(\Exception $e): void
    {
        $response = $this->app->response();

        if ($e->getMessage()) {
            $response->headers->set('X-Status-Reason', $e->getMessage());
        }
        $response->setStatus($e->getCode());
    }

    protected function processUnauthorized(): void
    {
        $response = $this->app->response();

        if ($this->showDialog) {
            $response->headers->set('WWW-Authenticate', 'Basic realm=""');
        }
        $response->setStatus(401);
    }

    protected function isXMLHttpRequest(): bool
    {
        $request = $this->app->request();

        $httpXRequestedWith = $request->headers('HTTP_X_REQUESTED_WITH');

        if (isset($httpXRequestedWith) && strtolower($httpXRequestedWith) == 'xmlhttprequest') {
            return true;
        }

        return false;
    }
}
