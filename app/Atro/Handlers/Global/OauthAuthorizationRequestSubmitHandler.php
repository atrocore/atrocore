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

use Atro\Core\Authentication\UserAuthenticator;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\OAuth\Authorization;
use Atro\Core\OAuth\SessionCookie;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/authorizationRequest',
    methods: [
        'POST',
    ],
    summary: 'Approve or deny an OAuth authorization request',
    description: 'Completes the authorization request kept in its cookie by the authorization endpoint GET /api/oauth/authorize. To approve it the user signs in with the username and password; denying needs no credentials. Returns the redirect URI of the client with the authorization code or with the "access_denied" error (RFC 6749, section 4.1.2). Called by the sign-in form of the authorization request.',
    tag: 'Global',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'approved',
                    ],
                    'properties' => [
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
    rateLimit: [
        'limit'         => 10,
        'period'        => 60,
        'failuresOnly'  => true,
        'userNameField' => 'username',
    ],
)]
class OauthAuthorizationRequestSubmitHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $authorization = $this->container->get(Authorization::class);

        $query = $authorization->decryptRequest((string)($request->getCookieParams()[SessionCookie::AUTHORIZATION_REQUEST] ?? ''));
        if ($query === null) {
            throw new BadRequest($this->getLanguage()->translate('signInExpired', 'messages', 'OauthClient'));
        }

        try {
            $authorizationRequest = $authorization->validateQuery($query);

            if (empty($data->approved)) {
                return $this->redirectResponse($request, $authorization->deny($authorizationRequest));
            }

            $user = $this->container->get(UserAuthenticator::class)
                ->authenticate((string)($data->username ?? ''), (string)($data->password ?? ''), $request);

            if ($user === null) {
                throw new BadRequest($this->getLanguage()->translate('wrongUsernamePasword', 'messages', 'User'));
            }

            return $this->redirectResponse($request, $authorization->approve($authorizationRequest, $user->id));
        } catch (OAuthServerException $e) {
            throw new BadRequest($e->getHint() ?: $e->getMessage());
        }
    }

    /**
     * The authorization request is completed, its cookie is not needed anymore.
     */
    private function redirectResponse(ServerRequestInterface $request, string $redirectUri): ResponseInterface
    {
        return (new JsonResponse(['redirectUri' => $redirectUri]))
            ->withHeader('Set-Cookie', SessionCookie::buildAuthorizationRequestClearHeaders($request, $this->getConfig()->getSiteUrl()));
    }
}
