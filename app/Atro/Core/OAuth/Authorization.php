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
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The authorization request of the authorization code grant (RFC 6749, section 4.1). PKCE with the S256 method is
 * required from every client (OAuth 2.1). The response type and the code challenge are validated here, after the client
 * and its redirect URI, so an invalid one is reported to the client by its redirect URI (RFC 6749, section 4.1.2.1).
 */
class Authorization
{
    use CryptTrait;

    public const RESPONSE_TYPE = 'code';
    public const CODE_CHALLENGE_METHOD = 'S256';

    /**
     * The code challenge is the base64url encoded SHA-256 hash of the code verifier (RFC 7636, section 4.2).
     */
    private const CODE_CHALLENGE_PATTERN = '/^[A-Za-z0-9\-._~]{43,128}$/';

    /**
     * How long the authorization request is kept for the sign-in form.
     */
    public const REQUEST_TTL = 600;

    public function __construct(private readonly AuthorizationServer $authorizationServer, Keys $keys)
    {
        $this->setEncryptionKey($keys->getEncryptionKey());
    }

    /**
     * @throws OAuthServerException
     */
    public function validate(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $params = $request->getQueryParams();
        $responseType = $params['response_type'] ?? null;
        $codeChallenge = $params['code_challenge'] ?? null;
        $codeChallengeMethod = $params['code_challenge_method'] ?? null;
        unset($params['code_challenge'], $params['code_challenge_method']);

        // the grant is chosen by the response type, the one of the request is validated after the client
        $params['response_type'] = self::RESPONSE_TYPE;

        $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($request->withQueryParams($params));

        if ($responseType === null || $responseType === '') {
            throw $this->createClientError(
                OAuthServerException::invalidRequest('response_type'),
                $authorizationRequest
            );
        }

        if ($responseType !== self::RESPONSE_TYPE) {
            throw $this->createClientError(
                new OAuthServerException(
                    'The authorization server does not support obtaining an authorization code using this method.',
                    2,
                    'unsupported_response_type',
                    400,
                    'Only the "' . self::RESPONSE_TYPE . '" response type is supported'
                ),
                $authorizationRequest
            );
        }

        if (
            !is_string($codeChallenge)
            || preg_match(self::CODE_CHALLENGE_PATTERN, $codeChallenge) !== 1
            || $codeChallengeMethod !== self::CODE_CHALLENGE_METHOD
        ) {
            throw $this->createClientError(
                OAuthServerException::invalidRequest(
                    'code_challenge',
                    'A code challenge (RFC 7636) with the "' . self::CODE_CHALLENGE_METHOD . '" method is required'
                ),
                $authorizationRequest
            );
        }

        $authorizationRequest->setCodeChallenge($codeChallenge);
        $authorizationRequest->setCodeChallengeMethod(self::CODE_CHALLENGE_METHOD);

        return $authorizationRequest;
    }

    /**
     * Validates the authorization request given by its query string.
     *
     * @throws OAuthServerException
     */
    public function validateQuery(string $query): AuthorizationRequestInterface
    {
        parse_str($query, $queryParams);

        return $this->validate((new ServerRequest('GET', '/api/oauth/authorize'))->withQueryParams($queryParams));
    }

    /**
     * The value of the cookie that keeps the query string of the authorization request for the sign-in form
     * (SessionCookie::AUTHORIZATION_REQUEST), so it does not travel in the URL of the page.
     */
    public function encryptRequest(string $query): string
    {
        return $this->encrypt(json_encode([
            'query'      => $query,
            'expireTime' => time() + self::REQUEST_TTL,
        ]));
    }

    /**
     * The query string of the authorization request kept by encryptRequest(), null when it is invalid or has expired.
     */
    public function decryptRequest(string $value): ?string
    {
        try {
            $request = json_decode($this->decrypt($value), true);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($request) || ($request['expireTime'] ?? 0) < time() || !isset($request['query'])) {
            return null;
        }

        return (string)$request['query'];
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
     * The identifier of the authorization code in a redirect URI returned by approve(), null when there is none.
     */
    public function getAuthorizationId(string $redirectUri): ?string
    {
        parse_str((string)parse_url($redirectUri, PHP_URL_QUERY), $params);

        return $this->getAuthCodeId((string)($params['code'] ?? ''));
    }

    /**
     * The identifier of an authorization code: the code is the payload encrypted by the authorization server.
     */
    public function getAuthCodeId(string $code): ?string
    {
        try {
            $payload = json_decode($this->decrypt($code), true);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($payload) && !empty($payload['auth_code_id']) ? (string)$payload['auth_code_id'] : null;
    }

    /**
     * Returns the URI the user agent is redirected to: the redirect URI of the client with the "access_denied" error
     * (RFC 6749, section 4.1.2.1).
     */
    public function deny(AuthorizationRequestInterface $authorizationRequest): string
    {
        return OAuthServerException::accessDenied('The user denied the request', $this->getClientRedirectUri($authorizationRequest))
            ->generateHttpResponse(new Response())
            ->getHeaderLine('Location');
    }

    /**
     * The error of the request, reported to the client by its redirect URI.
     */
    private function createClientError(OAuthServerException $error, AuthorizationRequestInterface $authorizationRequest): OAuthServerException
    {
        return new OAuthServerException(
            $error->getMessage(),
            $error->getCode(),
            $error->getErrorType(),
            $error->getHttpStatusCode(),
            $error->getHint(),
            $this->getClientRedirectUri($authorizationRequest)
        );
    }

    /**
     * The redirect URI of the request, or the one of the client, with the state of the request: an error is returned
     * there (RFC 6749, section 4.1.2.1).
     */
    private function getClientRedirectUri(AuthorizationRequestInterface $authorizationRequest): string
    {
        $redirectUri = $authorizationRequest->getRedirectUri();
        if ($redirectUri === null) {
            $clientRedirectUri = $authorizationRequest->getClient()->getRedirectUri();
            $redirectUri = is_array($clientRedirectUri) ? (string)reset($clientRedirectUri) : $clientRedirectUri;
        }

        if ($authorizationRequest->getState() !== null) {
            $redirectUri .= (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query(['state' => $authorizationRequest->getState()]);
        }

        return $redirectUri;
    }
}
