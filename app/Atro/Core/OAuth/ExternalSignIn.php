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
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The sign-in by an external identity provider, e.g. single sign-on: the user leaves the sign-in form for the provider
 * and comes back signed in. That approves the authorization request the form was shown for, kept in its cookie
 * (SessionCookie::AUTHORIZATION_REQUEST) meanwhile; without one the user signs in to the user interface and returns to
 * the route the sign-in started from.
 *
 * start() keeps the data of the provider, e.g. a nonce, in the cookie SessionCookie::EXTERNAL_SIGN_IN under a random
 * state, read() takes it back when the provider returns, complete() signs the user in, fail() returns to the sign-in
 * form with the error. A module that keeps data of the sign-in, e.g. the tokens of the provider, stores it in its own
 * table and links it to the session of the user interface by the events "OauthSession.afterCreate" and
 * "OauthSession.beforeRefresh".
 */
class ExternalSignIn
{
    use CryptTrait;

    private const TTL = 600;

    public function __construct(
        private readonly Authorization $authorization,
        private readonly BrowserSession $browserSession,
        private readonly Config $config,
        Keys $keys
    ) {
        $this->setEncryptionKey($keys->getEncryptionKey());
    }

    /**
     * The state to pass to the provider and the value of the cookie that keeps the data of the provider, e.g. a nonce,
     * and the route of the user interface (its URL fragment) to return to.
     *
     * @return array{state: string, cookie: string}
     */
    public function start(array $data = [], string $returnHash = ''): array
    {
        $state = bin2hex(random_bytes(16));

        return [
            'state'  => $state,
            'cookie' => $this->encrypt(json_encode([
                'state'      => $state,
                'data'       => $data,
                'returnHash' => str_starts_with($returnHash, '#') ? $returnHash : '',
                'expireTime' => time() + self::TTL,
            ])),
        ];
    }

    /**
     * The data kept by start(), null when the cookie does not belong to the state or has expired.
     */
    public function read(string $cookie, string $state): ?array
    {
        $value = $this->decryptCookie($cookie);

        if ($value === null || ($value['expireTime'] ?? 0) < time() || !hash_equals((string)($value['state'] ?? ''), $state)) {
            return null;
        }

        return is_array($value['data'] ?? null) ? $value['data'] : [];
    }

    /**
     * Signs in the user who came back from the provider (the request has the cookies of the sign-in): approves the
     * authorization request of the sign-in form, or, when there is none, the one of the user interface, whose session
     * the callback /api/oauth/callback opens. The identifier of the issued authorization code links the data of the
     * sign-in kept by the module to that session: the event "OauthSession.afterCreate" passes it with the ID of the
     * session.
     *
     * The response leads the user agent to the redirect URI of the client.
     *
     * @return array{response: ResponseInterface, authorizationId: string|null}
     * @throws OAuthServerException
     */
    public function complete(ServerRequestInterface $request, string $userId): array
    {
        $cookies = $request->getCookieParams();
        $siteUrl = $this->config->getSiteUrl();

        $query = $this->authorization->decryptRequest((string)($cookies[SessionCookie::AUTHORIZATION_REQUEST] ?? ''));
        if ($query !== null) {
            $redirectUri = $this->authorization->approve($this->authorization->validateQuery($query), $userId);
            $setCookie = SessionCookie::buildAuthorizationRequestClearHeaders($request, $siteUrl);
        } else {
            $signIn = $this->decryptCookie((string)($cookies[SessionCookie::EXTERNAL_SIGN_IN] ?? ''));
            $login = $this->browserSession->startLogin((string)($signIn['returnHash'] ?? ''));

            $redirectUri = $this->authorization->approve($this->authorization->validateQuery($login['query']), $userId);
            $setCookie = SessionCookie::buildLoginHeader($request, $siteUrl, $login['loginCookie']);
        }

        return [
            'response'        => (new Response(302))
                ->withHeader('Location', $redirectUri)
                ->withHeader('Set-Cookie', $setCookie),
            'authorizationId' => $this->authorization->getAuthorizationId($redirectUri),
        ];
    }

    /**
     * Returns to the user interface, whose sign-in form shows the error.
     */
    public function fail(ServerRequestInterface $request, string $message): ResponseInterface
    {
        $siteUrl = $this->config->getSiteUrl();

        return (new Response(302))
            ->withHeader('Location', rtrim($siteUrl, '/') . '/')
            ->withHeader('Set-Cookie', SessionCookie::buildSignInErrorHeader($request, $siteUrl, $message));
    }

    private function decryptCookie(string $cookie): ?array
    {
        try {
            $value = json_decode($this->decrypt($cookie), true);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($value) ? $value : null;
    }
}
