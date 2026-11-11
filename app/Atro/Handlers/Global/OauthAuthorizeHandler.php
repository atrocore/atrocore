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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\OAuth\Authorization;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/authorize',
    methods: [
        'GET',
    ],
    summary: 'Validate an OAuth authorization request',
    description: 'Validates the authorization request of the authorization code grant (RFC 6749, section 4.1.1) and returns the client that requests the access. Called by the sign-in page that the authorization endpoint /oauth/authorize leads to.',
    tag: 'Global',
    auth: false,
    parameters: [
        [
            'name'     => 'response_type',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
                'enum' => [
                    'code',
                ],
            ],
        ],
        [
            'name'     => 'client_id',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'redirect_uri',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'state',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'code_challenge',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'code_challenge_method',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
                'enum' => [
                    'S256',
                ],
            ],
        ],
        [
            'name'     => 'scope',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The client that requests the access.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'clientName' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The authorization request is invalid.',
        ],
    ],
    skipActionHistory: true,
)]
class OauthAuthorizeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $authorizationRequest = $this->container->get(Authorization::class)->validate($request);
        } catch (OAuthServerException $e) {
            throw new BadRequest($e->getHint() ?: $e->getMessage());
        }

        return new JsonResponse([
            'clientName' => $authorizationRequest->getClient()->getName(),
        ]);
    }
}
