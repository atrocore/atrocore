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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\OAuth\BrowserSession;
use Atro\Core\OAuth\SessionCookie;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/signIn',
    methods: [
        'POST',
    ],
    summary: 'Sign in to the user interface',
    description: 'Signs the user in with the username and password and opens the session of the user interface: the server, the OAuth client of the user interface, gets the tokens by the authorization code grant with PKCE and sets the session cookies. Called by the sign-in form of the user interface.',
    tag: 'Global',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'username',
                        'password',
                    ],
                    'properties' => [
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
            'description' => 'true when the session is opened',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The credentials are wrong or signing in to the user interface is turned off.',
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
class OauthSignInHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $user = $this->container->get(UserAuthenticator::class)
            ->authenticate((string)($data->username ?? ''), (string)($data->password ?? ''), $request);

        if ($user === null) {
            throw new BadRequest($this->getLanguage()->translate('wrongUsernamePasword', 'messages', 'User'));
        }

        try {
            $sessionId = $this->container->get(BrowserSession::class)
                ->signIn($user->id, (string)($request->getServerParams()['REMOTE_ADDR'] ?? ''));
        } catch (OAuthServerException $e) {
            throw new BadRequest($this->getLanguage()->translate('uiClientDisabled', 'messages', 'OauthClient'));
        }

        return (new BoolResponse(true))
            ->withHeader('Set-Cookie', SessionCookie::buildSessionHeaders($request, $this->getConfig()->getSiteUrl(), $sessionId));
    }
}
