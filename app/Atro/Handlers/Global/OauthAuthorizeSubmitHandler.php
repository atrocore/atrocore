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
use Atro\Core\OAuth\UserAuthenticator;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/authorize',
    methods: [
        'POST',
    ],
    summary: 'Approve or deny an OAuth authorization request',
    description: 'Completes the authorization request of the authorization code grant. To approve it the user signs in with the username and password; denying needs no credentials. Returns the redirect URI of the client with the authorization code or with the "access_denied" error (RFC 6749, section 4.1.2). Called by the sign-in page.',
    tag: 'Global',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'query',
                        'approved',
                    ],
                    'properties' => [
                        'query'    => [
                            'type'        => 'string',
                            'description' => 'The query string of the authorization request.',
                        ],
                        'approved' => [
                            'type' => 'boolean',
                        ],
                        'username' => [
                            'type' => 'string',
                        ],
                        'password' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The URI to redirect the user agent to.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'redirectUri' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The authorization request is invalid or the credentials are wrong.',
        ],
    ],
    skipActionHistory: true,
    rateLimit: [],
)]
class OauthAuthorizeSubmitHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        parse_str((string)$data->query, $queryParams);
        $authorizationRequest = $request->withQueryParams($queryParams);

        $authorization = $this->container->get(Authorization::class);

        try {
            $authorizationRequest = $authorization->validate($authorizationRequest);

            if (empty($data->approved)) {
                return new JsonResponse(['redirectUri' => $authorization->deny($authorizationRequest)]);
            }

            $user = $this->container->get(UserAuthenticator::class)
                ->authenticate((string)($data->username ?? ''), (string)($data->password ?? ''), $request);

            if ($user === null) {
                throw new BadRequest($this->getLanguage()->translate('wrongUsernamePasword', 'messages', 'User'));
            }

            return new JsonResponse(['redirectUri' => $authorization->approve($authorizationRequest, $user->id)]);
        } catch (OAuthServerException $e) {
            throw new BadRequest($e->getHint() ?: $e->getMessage());
        }
    }
}
