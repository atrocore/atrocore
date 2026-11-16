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

use Atro\Core\OAuth\Repositories\ClientRepository;
use Atro\Core\OAuth\TokenReader;
use Atro\Core\OAuth\TokenRevoker;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/oauth/revoke',
    methods: [
        'POST',
    ],
    summary: 'Revoke an OAuth token',
    description: 'The token revocation endpoint (RFC 7009). Revokes an access token or a refresh token issued to the authenticated client; revoking a token also revokes the related tokens. The client authenticates by HTTP Basic authentication or by client_id and client_secret in the request body.',
    tag: 'Global',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => [
            'application/x-www-form-urlencoded' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'token',
                    ],
                    'properties' => [
                        'token'           => [
                            'type' => 'string',
                        ],
                        'token_type_hint' => [
                            'type' => 'string',
                            'enum' => [
                                'access_token',
                                'refresh_token',
                            ],
                        ],
                        'client_id'       => [
                            'type' => 'string',
                        ],
                        'client_secret'   => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The token is revoked, or it was invalid already.',
        ],
        401 => [
            'description' => 'Client authentication failed.',
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
)]
class OauthRevokeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        [$clientId, $clientSecret] = $this->getClientCredentials($request);

        if ($clientId === null || !$this->container->get(ClientRepository::class)->validateClient($clientId, $clientSecret, null)) {
            return OAuthServerException::invalidClient($request)->generateHttpResponse(new Response());
        }

        $body = (array)$request->getParsedBody();

        $this->revokeToken((string)($body['token'] ?? ''), $clientId);

        return new Response(200);
    }

    /**
     * A token that is invalid, already revoked or issued to another client is ignored (RFC 7009, section 2.2).
     */
    private function revokeToken(string $token, string $clientId): void
    {
        $reader = $this->container->get(TokenReader::class);
        $revoker = $this->container->get(TokenRevoker::class);

        $accessToken = $reader->readAccessToken($token);
        if ($accessToken !== null) {
            if ($accessToken['clientId'] === $clientId) {
                $revoker->revokeAccessToken($accessToken['identifier']);
            }
            return;
        }

        $refreshToken = $reader->readRefreshToken($token);
        if ($refreshToken !== null && $refreshToken['clientId'] === $clientId) {
            $revoker->revokeRefreshToken($refreshToken['identifier'], $refreshToken['accessTokenIdentifier']);
        }
    }

    /**
     * The client credentials from the HTTP Basic authentication or from the request body.
     */
    private function getClientCredentials(ServerRequestInterface $request): array
    {
        $header = $request->getHeaderLine('Authorization');
        if (stripos($header, 'Basic ') === 0) {
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$clientId, $clientSecret] = explode(':', $decoded, 2);

                return [rawurldecode($clientId), rawurldecode($clientSecret)];
            }
        }

        $body = (array)$request->getParsedBody();

        return [
            isset($body['client_id']) ? (string)$body['client_id'] : null,
            isset($body['client_secret']) ? (string)$body['client_secret'] : null,
        ];
    }
}
