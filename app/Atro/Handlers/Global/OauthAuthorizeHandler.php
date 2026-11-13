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
use Atro\Core\OAuth\Authorization;
use Atro\Core\OAuth\SessionCookie;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/authorize',
    methods: [
        'GET',
    ],
    summary: 'OAuth authorization endpoint',
    description: 'The authorization endpoint of the authorization code grant (RFC 6749, section 4.1): `response_type=code`, PKCE with `code_challenge_method=S256` is required. The client leads the user agent here; the authorization request is kept in an encrypted cookie and the user agent goes on to the user interface, which shows the sign-in form of the request. An error the client can be told of returns to its redirect URI.',
    tag: 'Global',
    auth: false,
    parameters: [
        [
            'name'     => 'response_type',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'client_id',
            'in'       => 'query',
            'required' => false,
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
            'name'     => 'scope',
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
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'code_challenge_method',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        302 => [
            'description' => 'To the user interface, or to the redirect URI of the client with an error.',
        ],
        400 => [
            'description' => 'The authorization request is invalid and the client cannot be told of it.',
        ],
    ],
    skipActionHistory: true,
)]
class OauthAuthorizeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $this->container->get(Authorization::class);
        $query = http_build_query($request->getQueryParams());

        try {
            $authorization->validateQuery($query);
        } catch (OAuthServerException $e) {
            if ($e->hasRedirect()) {
                return $e->generateHttpResponse(new Response());
            }

            throw new BadRequest($e->getHint() ?: $e->getMessage());
        }

        $siteUrl = $this->getConfig()->getSiteUrl();

        return (new Response(302))
            ->withHeader('Location', rtrim($siteUrl, '/') . '/')
            ->withHeader('Set-Cookie', SessionCookie::buildAuthorizationRequestHeaders($request, $siteUrl, $authorization->encryptRequest($query)));
    }
}
