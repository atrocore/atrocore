<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Handlers\Global;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\AuthCookie;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/userSession',
    methods: [
        'GET',
    ],
    summary: 'Get authorized user data',
    description: 'Generate authorization token and return authorized user data.',
    tag: 'Global',
    auth: true,
    parameters: [
        [
            'name'     => 'Authorization-Token-Only',
            'in'       => 'header',
            'required' => false,
            'schema'   => [
                'type'    => 'boolean',
                'example' => true,
            ],
        ],
        [
            'name'        => 'Authorization-Cookie',
            'in'          => 'header',
            'required'    => false,
            'description' => 'When true, the auth token is set as an HttpOnly cookie and is not returned in the response body. Used by the UI.',
            'schema'      => [
                'type'    => 'boolean',
                'example' => true,
            ],
        ],
        [
            'name'        => 'Authorization-Token-Lifetime',
            'in'          => 'header',
            'required'    => false,
            'description' => 'Lifetime should be set in hours. 0 means no expiration. If this parameter is not passed, the globally configured parameter is used.',
            'schema'      => [
                'type'    => 'integer',
                'example' => 0,
            ],
        ],
        [
            'name'        => 'Authorization-Token-Idletime',
            'in'          => 'header',
            'required'    => false,
            'description' => 'Idletime should be set in hours. 0 means no expiration. If this parameter is not passed, the globally configured parameter is used.',
            'schema'      => [
                'type'    => 'integer',
                'example' => 0,
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Authorized user data. When Authorization-Token-Only is true, only authorizationToken is returned. authorizationToken and token are omitted when the request is authenticated by the auth cookie or Authorization-Cookie is true.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'authorizationToken' => [
                                'type'        => 'string',
                                'description' => 'Base64-encoded "username:token" credential string.',
                                'example'     => 'YWRtaW46NGQ1NGU5ZTEzYjc0NGQzOGM5ODM2NzIyNDU2YTZmNjk=',
                            ],
                            'token'              => [
                                'type'        => 'string',
                                'description' => 'Raw authorization token.',
                            ],
                            'user'               => [
                                'type'                 => 'object',
                                'description'          => 'Authenticated user record.'
                            ],
                            'acl'                => [
                                'type'                 => 'object',
                                'description'          => 'ACL scope-permission map for the authenticated user.'
                            ],
                            'preferences'        => [
                                'type'        => 'object',
                                'description' => 'User UI preferences.'
                            ],
                            'settings'           => [
                                'type'                 => 'object',
                                'description'          => 'Application settings visible to the authenticated user.'
                            ],
                            'appParams'          => [
                                'type'        => 'object',
                                'description' => 'Runtime application parameters.'
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    skipActionHistory: true
)]
class UserHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getServiceFactory()->create('App')->getUserData();
        unset($data['user']->token);

        $cookieHeader = $request->getHeaderLine('Authorization-Cookie');
        $isCookieMode = $request->getAttribute('isCookieAuth') || $cookieHeader === 'true' || $cookieHeader === '1';

        $tokenOnly = $request->getHeaderLine('Authorization-Token-Only');
        if ($tokenOnly === 'true' || $tokenOnly === '1') {
            if ($isCookieMode) {
                throw new Forbidden('Authorization-Token-Only is not available in cookie mode');
            }
            return new JsonResponse(['authorizationToken' => base64_encode("{$data['user']->userName}:{$data['token']}")]);
        }

        if (!$isCookieMode) {
            $data['authorizationToken'] = base64_encode("{$data['user']->userName}:{$data['token']}");
            return new JsonResponse($data);
        }

        $cookieHeaders = AuthCookie::buildSetHeaders(
            $request,
            $this->getConfig()->getSiteUrl(),
            $data['user']->userName,
            $data['token']
        );
        unset($data['token']);

        return (new JsonResponse($data))->withHeader('Set-Cookie', $cookieHeaders);
    }
}
