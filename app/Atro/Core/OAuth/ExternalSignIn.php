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

use Atro\Core\Utils\Config;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * The sign-in by an external identity provider, e.g. single sign-on: the user leaves the sign-in page of an
 * authorization request for the provider and comes back signed in, which approves the authorization request.
 *
 * start() keeps the authorization request in the cookie SessionCookie::EXTERNAL_SIGN_IN under a random state, read()
 * takes it back when the provider returns, complete() approves it for the user. A module that keeps data of the sign-in,
 * e.g. the tokens of the provider, stores it in its own table and links it to the session of the user interface by the
 * events "OauthSession.afterCreate" and "OauthSession.beforeRefresh".
 */
class ExternalSignIn
{
    use CryptTrait;

    private const TTL = 600;

    public function __construct(private readonly Authorization $authorization, private readonly Config $config, Keys $keys)
    {
        $this->setEncryptionKey($keys->getEncryptionKey());
    }

    /**
     * The state to pass to the provider and the value of the cookie that keeps the authorization request and the data
     * of the provider, e.g. a nonce.
     *
     * @return array{state: string, cookie: string}
     */
    public function start(string $authorizationQuery, array $data = []): array
    {
        $state = bin2hex(random_bytes(16));

        return [
            'state'  => $state,
            'cookie' => $this->encrypt(json_encode([
                'state'              => $state,
                'authorizationQuery' => $authorizationQuery,
                'data'               => $data,
                'expireTime'         => time() + self::TTL,
            ])),
        ];
    }

    /**
     * The authorization request and the data kept by start(), null when the cookie does not belong to the state or has
     * expired.
     *
     * @return array{authorizationQuery: string, data: array}|null
     */
    public function read(string $cookie, string $state): ?array
    {
        try {
            $value = json_decode($this->decrypt($cookie), true);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($value) || ($value['expireTime'] ?? 0) < time() || !hash_equals((string)($value['state'] ?? ''), $state)) {
            return null;
        }

        return [
            'authorizationQuery' => (string)($value['authorizationQuery'] ?? ''),
            'data'               => is_array($value['data'] ?? null) ? $value['data'] : [],
        ];
    }

    /**
     * Approves the authorization request for the signed-in user. The identifier of the issued authorization code links
     * the data of the sign-in kept by the module, e.g. the tokens of the provider, to the session of the user interface
     * opened by the code: the event "OauthSession.afterCreate" passes it with the ID of the session.
     *
     * @return array{redirectUri: string, authorizationId: string|null} The URI to redirect the user agent to and the
     *                                                                    identifier of the authorization code.
     * @throws OAuthServerException
     */
    public function complete(string $authorizationQuery, string $userId): array
    {
        parse_str($authorizationQuery, $queryParams);

        $request = (new ServerRequest('GET', '/oauth/authorize'))->withQueryParams($queryParams);

        $redirectUri = $this->authorization->approve($this->authorization->validate($request), $userId);

        return [
            'redirectUri'     => $redirectUri,
            'authorizationId' => $this->authorization->getAuthorizationId($redirectUri),
        ];
    }

    /**
     * The sign-in page of the authorization request with an error of the external sign-in.
     */
    public function getSignInPageUrl(string $authorizationQuery, string $error): string
    {
        return rtrim($this->config->getSiteUrl(), '/') . '/oauth/authorize?' . $authorizationQuery . '&' . http_build_query(['ssoError' => $error]);
    }
}
