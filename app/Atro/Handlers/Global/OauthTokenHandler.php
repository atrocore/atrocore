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

use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/token',
    methods: [
        'POST',
    ],
    summary: 'Issue an OAuth access token',
    description: 'The token endpoint of the OAuth 2.0 authorization server (RFC 6749). The client authenticates by HTTP Basic authentication or by client_id and client_secret in the request body.',
    tag: 'Global',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => [
            'application/x-www-form-urlencoded' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'grant_type',
                    ],
                    'properties' => [
                        'grant_type'    => [
                            'type' => 'string',
                            'enum' => [
                                'authorization_code',
                                'client_credentials',
                                'refresh_token',
                            ],
                        ],
                        'client_id'     => [
                            'type' => 'string',
                        ],
                        'client_secret' => [
                            'type' => 'string',
                        ],
                        'refresh_token' => [
                            'type' => 'string',
                        ],
                        'code'          => [
                            'type' => 'string',
                        ],
                        'redirect_uri'  => [
                            'type' => 'string',
                        ],
                        'code_verifier' => [
                            'type' => 'string',
                        ],
                        'scope'         => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The issued access token.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'token_type'    => [
                                'type'    => 'string',
                                'example' => 'Bearer',
                            ],
                            'expires_in'    => [
                                'type' => 'integer',
                            ],
                            'access_token'  => [
                                'type' => 'string',
                            ],
                            'refresh_token' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Invalid request (RFC 6749, section 5.2).',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
        401 => [
            'description' => 'Client authentication failed (RFC 6749, section 5.2).',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
    ],
    skipActionHistory: true,
    rateLimit: [],
)]
class OauthTokenHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $this->container->get(AuthorizationServer::class)->respondToAccessTokenRequest($request, new Response());
        } catch (OAuthServerException $e) {
            return $e->generateHttpResponse(new Response());
        }
    }
}
