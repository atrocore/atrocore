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
use Atro\Core\OAuth\Repositories\AuthCodeRepository;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The authorization request of the authorization code grant (RFC 6749, section 4.1). PKCE with the S256 method is
 * required from every client (OAuth 2.1).
 */
class Authorization
{
    use CryptTrait;

    public const CODE_CHALLENGE_METHOD = 'S256';

    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly Connection $connection,
        Keys $keys
    ) {
        $this->setEncryptionKey($keys->getEncryptionKey());
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
     * @param array|null $signInData The data of the sign-in the session of the user interface depends on, e.g. the
     *                               tokens of an external identity provider (see BrowserSession).
     *
     * @throws OAuthServerException
     */
    public function approve(AuthorizationRequestInterface $authorizationRequest, string $userId, ?array $signInData = null): string
    {
        $authorizationRequest->setUser(new UserEntity($userId));
        $authorizationRequest->setAuthorizationApproved(true);

        $location = $this->authorizationServer
            ->completeAuthorizationRequest($authorizationRequest, new Response())
            ->getHeaderLine('Location');

        if ($signInData !== null) {
            $this->storeSignInData($location, $signInData);
        }

        return $location;
    }

    /**
     * The data of the sign-in of the authorization code, null when there is none (see approve()).
     */
    public function getSignInData(string $code): ?string
    {
        $authCodeId = $this->getAuthCodeId($code);
        if ($authCodeId === null) {
            return null;
        }

        $signInData = $this->connection->createQueryBuilder()
            ->select('sign_in_data')
            ->from(AuthCodeRepository::TABLE)
            ->where('identifier = :identifier')
            ->setParameter('identifier', $authCodeId)
            ->fetchOne();

        return $signInData ?: null;
    }

    /**
     * The sign-in data is stored encrypted with the authorization code issued in the redirect URI.
     */
    private function storeSignInData(string $location, array $signInData): void
    {
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $authCodeId = $this->getAuthCodeId((string)($params['code'] ?? ''));
        if ($authCodeId === null) {
            return;
        }

        $this->connection->createQueryBuilder()
            ->update(AuthCodeRepository::TABLE)
            ->set('sign_in_data', ':signInData')
            ->where('identifier = :identifier')
            ->setParameter('signInData', $this->encrypt(json_encode($signInData)))
            ->setParameter('identifier', $authCodeId)
            ->executeStatement();
    }

    /**
     * An authorization code is the payload encrypted by the authorization server.
     */
    private function getAuthCodeId(string $code): ?string
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
