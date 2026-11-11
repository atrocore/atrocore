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

namespace Atro\Core\OAuth;

use Atro\Core\OAuth\Entities\UserEntity;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The authorization request of the authorization code grant (RFC 6749, section 4.1). PKCE with the S256 method is
 * required from every client (OAuth 2.1).
 */
class Authorization
{
    public const CODE_CHALLENGE_METHOD = 'S256';

    public function __construct(private readonly AuthorizationServer $authorizationServer)
    {
    }

    /**
     * @throws OAuthServerException
     */
    public function validate(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($request);

        if ($authorizationRequest->getCodeChallengeMethod() !== self::CODE_CHALLENGE_METHOD) {
            throw OAuthServerException::invalidRequest(
                'code_challenge_method',
                'A code challenge with the "' . self::CODE_CHALLENGE_METHOD . '" method is required'
            );
        }

        return $authorizationRequest;
    }

    /**
     * Returns the URI the user agent is redirected to: the redirect URI of the client with the authorization code.
     *
     * @throws OAuthServerException
     */
    public function approve(AuthorizationRequestInterface $authorizationRequest, string $userId): string
    {
        $authorizationRequest->setUser(new UserEntity($userId));
        $authorizationRequest->setAuthorizationApproved(true);

        return $this->authorizationServer
            ->completeAuthorizationRequest($authorizationRequest, new Response())
            ->getHeaderLine('Location');
    }

    /**
     * Returns the URI the user agent is redirected to: the redirect URI of the client with the "access_denied" error
     * (RFC 6749, section 4.1.2.1).
     */
    public function deny(AuthorizationRequestInterface $authorizationRequest): string
    {
        $redirectUri = $authorizationRequest->getRedirectUri();
        if ($redirectUri === null) {
            $clientRedirectUri = $authorizationRequest->getClient()->getRedirectUri();
            $redirectUri = is_array($clientRedirectUri) ? (string)reset($clientRedirectUri) : $clientRedirectUri;
        }

        if ($authorizationRequest->getState() !== null) {
            $redirectUri .= (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query(['state' => $authorizationRequest->getState()]);
        }

        return OAuthServerException::accessDenied('The user denied the request', $redirectUri)
            ->generateHttpResponse(new Response())
            ->getHeaderLine('Location');
    }
}
