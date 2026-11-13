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

use Atro\Core\Container;
use Atro\Core\EventManager\Event;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * The sessions of the user interface (backend for frontend): the server is the OAuth client of the user interface,
 * keeps its tokens encrypted and gives the browser only the session cookie. The access token is refreshed when it
 * expires, the session ends with the lifetime and the idle time from the settings.
 */
class BrowserSession
{
    use CryptTrait;

    public const TABLE = 'oauth_session';

    /**
     * The access token is refreshed this many seconds before it expires.
     */
    private const REFRESH_MARGIN = 30;

    /**
     * The last access of a session is stored at most once in this many seconds.
     */
    private const LAST_ACCESS_INTERVAL = 60;

    private const LOGIN_TTL = 600;

    public function __construct(
        private readonly Connection $connection,
        private readonly Container $container,
        private readonly Config $config,
        private readonly UiClient $uiClient,
        private readonly TokenReader $tokenReader,
        private readonly TokenRevoker $tokenRevoker,
        Keys $keys
    ) {
        $this->setEncryptionKey($keys->getEncryptionKey());
    }

    /**
     * The authorization request of the user interface (with PKCE) and the encrypted value of the login cookie that
     * keeps its state and code verifier until the callback.
     *
     * @return array{authorizationUrl: string, loginCookie: string}
     */
    public function startLogin(string $returnHash): array
    {
        $codeVerifier = $this->base64UrlEncode(random_bytes(48));
        $state = bin2hex(random_bytes(16));

        $query = http_build_query([
            'response_type'         => 'code',
            'client_id'             => $this->uiClient->getId(),
            'redirect_uri'          => $this->uiClient->getRedirectUri(),
            'state'                 => $state,
            'code_challenge'        => $this->base64UrlEncode(hash('sha256', $codeVerifier, true)),
            'code_challenge_method' => Authorization::CODE_CHALLENGE_METHOD,
        ]);

        return [
            'authorizationUrl' => rtrim($this->config->getSiteUrl(), '/') . '/oauth/authorize?' . $query,
            'loginCookie'      => $this->encrypt(json_encode([
                'state'        => $state,
                'codeVerifier' => $codeVerifier,
                'returnHash'   => $returnHash,
                'expireTime'   => time() + self::LOGIN_TTL,
            ])),
        ];
    }

    /**
     * Exchanges the authorization code for the tokens and opens the session.
     *
     * @return array{sessionId: string, returnHash: string}|null Null when the callback does not belong to the login.
     * @throws OAuthServerException
     */
    public function completeLogin(string $code, string $state, string $loginCookie, string $ipAddress): ?array
    {
        try {
            $login = json_decode($this->decrypt($loginCookie), true);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($login) || ($login['expireTime'] ?? 0) < time() || !hash_equals((string)($login['state'] ?? ''), $state)) {
            return null;
        }

        $tokens = $this->requestTokens([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->uiClient->getRedirectUri(),
            'code_verifier' => (string)$login['codeVerifier'],
        ]);

        $accessToken = $this->tokenReader->readAccessToken((string)$tokens['access_token']);
        if ($accessToken === null) {
            return null;
        }


        if (!empty($this->config->get('sessionPreventConcurrent'))) {
            $this->endUserSessions($accessToken['userId']);
        }

        $sessionId = bin2hex(random_bytes(32));
        $id = IdGenerator::uuid();
        $now = date('Y-m-d H:i:s');

        $this->connection->createQueryBuilder()
            ->insert(self::TABLE)
            ->values([
                'id'                      => ':id',
                'identifier'              => ':identifier',
                'user_id'                 => ':userId',
                'access_token'            => ':accessToken',
                'access_token_expires_at' => ':accessTokenExpiresAt',
                'refresh_token'           => ':refreshToken',
                'ip_address'              => ':ipAddress',
                'last_access_at'          => ':now',
                'created_at'              => ':now',
            ])
            ->setParameter('id', $id)
            ->setParameter('identifier', $this->hashSessionId($sessionId))
            ->setParameter('userId', $accessToken['userId'])
            ->setParameter('accessToken', $this->encrypt((string)$tokens['access_token']))
            ->setParameter('accessTokenExpiresAt', $this->getExpireTime($tokens))
            ->setParameter('refreshToken', $this->encrypt((string)($tokens['refresh_token'] ?? '')))
            ->setParameter('ipAddress', $ipAddress)
            ->setParameter('now', $now)
            ->executeStatement();

        $this->updateUserLastAccess($accessToken['userId'], $now);

        // a module of an external sign-in links its data, e.g. the tokens of the provider, to the session
        $this->container->get('eventManager')->dispatch('OauthSession', 'afterCreate', new Event([
            'sessionId'       => $id,
            'authorizationId' => $this->container->get(Authorization::class)->getAuthCodeId($code),
            'userId'          => $accessToken['userId'],
        ]));

        return [
            'sessionId'  => $sessionId,
            'returnHash' => (string)($login['returnHash'] ?? ''),
        ];
    }

    /**
     * The data of the access token of the session (see TokenReader::readAccessToken()), refreshed when it expires.
     * Null when the session does not exist or has ended.
     */
    public function authenticate(string $sessionId): ?array
    {
        $session = $this->connection->createQueryBuilder()
            ->select('id', 'access_token', 'access_token_expires_at', 'refresh_token', 'last_access_at', 'created_at')
            ->from(self::TABLE)
            ->where('identifier = :identifier')
            ->setParameter('identifier', $this->hashSessionId($sessionId))
            ->fetchAssociative();

        if (empty($session)) {
            return null;
        }

        if ($this->isOver($session)) {
            $this->endSession($session);
            return null;
        }

        if (strtotime((string)$session['access_token_expires_at']) - self::REFRESH_MARGIN <= time()) {
            $session = $this->refresh((string)$session['id']);
            if ($session === null) {
                return null;
            }
        }

        $accessToken = $this->tokenReader->readAccessToken($this->decryptToken($session['access_token']));
        if ($accessToken === null) {
            return null;
        }

        if (strtotime((string)$session['last_access_at']) + self::LAST_ACCESS_INTERVAL <= time()) {
            $now = date('Y-m-d H:i:s');

            $this->connection->createQueryBuilder()
                ->update(self::TABLE)
                ->set('last_access_at', ':now')
                ->where('id = :id')
                ->setParameter('now', $now)
                ->setParameter('id', $session['id'])
                ->executeStatement();

            $this->updateUserLastAccess($accessToken['userId'], $now);
        }

        return $accessToken;
    }

    public function logout(string $sessionId): void
    {
        $session = $this->connection->createQueryBuilder()
            ->select('id', 'refresh_token')
            ->from(self::TABLE)
            ->where('identifier = :identifier')
            ->setParameter('identifier', $this->hashSessionId($sessionId))
            ->fetchAssociative();

        if (!empty($session)) {
            $this->endSession($session);
        }
    }

    /**
     * Ends the sessions that are over their lifetime or idle time.
     */
    public function deleteExpired(): void
    {
        $lifetime = (float)$this->config->get('sessionLifetime', 0);
        $idleTime = (float)$this->config->get('sessionMaxIdleTime', 0);

        if ($lifetime <= 0 && $idleTime <= 0) {
            return;
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('id', 'refresh_token')
            ->from(self::TABLE);

        $conditions = [];
        if ($lifetime > 0) {
            $conditions[] = 'created_at < :createdBefore';
            $queryBuilder->setParameter('createdBefore', date('Y-m-d H:i:s', time() - (int)round($lifetime * 3600)));
        }
        if ($idleTime > 0) {
            $conditions[] = 'last_access_at < :accessedBefore';
            $queryBuilder->setParameter('accessedBefore', date('Y-m-d H:i:s', time() - (int)round($idleTime * 3600)));
        }

        $queryBuilder->where($queryBuilder->expr()->or(...$conditions));

        foreach ($queryBuilder->fetchAllAssociative() as $session) {
            $this->endSession($session);
        }
    }

    /**
     * Refreshes the tokens of the session. The row is locked: the parallel requests of the user interface would
     * otherwise refresh with the same refresh token, which the first refresh revokes.
     */
    private function refresh(string $id): ?array
    {
        $this->connection->beginTransaction();

        try {
            $select = $this->connection->createQueryBuilder()
                ->select('id', 'access_token', 'access_token_expires_at', 'refresh_token', 'last_access_at')
                ->from(self::TABLE)
                ->where('id = :id');

            $session = $this->connection->executeQuery($select->getSQL() . ' FOR UPDATE', ['id' => $id])->fetchAssociative();

            if (empty($session) || strtotime((string)$session['access_token_expires_at']) - self::REFRESH_MARGIN > time()) {
                $this->connection->commit();
                return $session ?: null;
            }

            if (!$this->isSignInValid($id)) {
                $this->connection->rollBack();
                $this->endSession($session);

                return null;
            }

            $tokens = $this->requestTokens([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->decryptToken($session['refresh_token']),
            ]);

            $session['access_token'] = $this->encrypt((string)$tokens['access_token']);
            $session['access_token_expires_at'] = $this->getExpireTime($tokens);
            $session['refresh_token'] = $this->encrypt((string)($tokens['refresh_token'] ?? ''));

            $this->connection->createQueryBuilder()
                ->update(self::TABLE)
                ->set('access_token', ':accessToken')
                ->set('access_token_expires_at', ':accessTokenExpiresAt')
                ->set('refresh_token', ':refreshToken')
                ->where('id = :id')
                ->setParameter('accessToken', $session['access_token'])
                ->setParameter('accessTokenExpiresAt', $session['access_token_expires_at'])
                ->setParameter('refreshToken', $session['refresh_token'])
                ->setParameter('id', $id)
                ->executeStatement();

            $this->connection->commit();

            return $session;
        } catch (OAuthServerException $e) {
            // the refresh token is revoked or expired: the session is over
            $this->connection->rollBack();
            $this->deleteSession($id);

            return null;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * A session opened by an external sign-in lasts while the sign-in is valid: the module of the sign-in checks it in
     * the listener of "OauthSession.beforeRefresh" (e.g. refreshes the tokens of the identity provider it keeps for the
     * session) and sets "isValid" to false to end the session.
     */
    private function isSignInValid(string $sessionId): bool
    {
        $event = $this->container->get('eventManager')->dispatch('OauthSession', 'beforeRefresh', new Event([
            'sessionId' => $sessionId,
            'isValid'   => true,
        ]));

        return !empty($event->getArgument('isValid'));
    }

    /**
     * @throws OAuthServerException
     */
    private function requestTokens(array $params): array
    {
        $request = (new ServerRequest('POST', '/api/oauth/token'))
            ->withParsedBody(array_merge($params, ['client_id' => (string)$this->uiClient->getId()]));

        $response = $this->container->get(AuthorizationServer::class)->respondToAccessTokenRequest($request, new Response());

        return json_decode((string)$response->getBody(), true);
    }

    private function isOver(array $session): bool
    {
        $lifetime = (float)$this->config->get('sessionLifetime', 0);
        if ($lifetime > 0 && strtotime((string)$session['created_at']) + (int)round($lifetime * 3600) < time()) {
            return true;
        }

        $idleTime = (float)$this->config->get('sessionMaxIdleTime', 0);

        return $idleTime > 0 && strtotime((string)$session['last_access_at']) + (int)round($idleTime * 3600) < time();
    }

    /**
     * Ends all sessions of the user, e.g. when the user is deactivated.
     */
    public function endUserSessions(string $userId): void
    {
        $sessions = $this->connection->createQueryBuilder()
            ->select('id', 'refresh_token')
            ->from(self::TABLE)
            ->where('user_id = :userId')
            ->setParameter('userId', $userId)
            ->fetchAllAssociative();

        foreach ($sessions as $session) {
            $this->endSession($session);
        }
    }

    /**
     * The last access of the user is kept after the session ends.
     */
    private function updateUserLastAccess(string $userId, string $now): void
    {
        $this->connection->createQueryBuilder()
            ->update($this->connection->quoteIdentifier('user'))
            ->set('last_access', ':now')
            ->where('id = :id')
            ->setParameter('now', $now)
            ->setParameter('id', $userId)
            ->executeStatement();
    }

    /**
     * Revokes the tokens of the session and deletes it.
     */
    private function endSession(array $session): void
    {
        $refreshToken = $this->tokenReader->readRefreshToken($this->decryptToken($session['refresh_token']));
        if ($refreshToken !== null) {
            $this->tokenRevoker->revokeRefreshToken($refreshToken['identifier'], $refreshToken['accessTokenIdentifier']);
        }

        $this->deleteSession((string)$session['id']);
    }

    private function deleteSession(string $id): void
    {
        $this->connection->createQueryBuilder()
            ->delete(self::TABLE)
            ->where('id = :id')
            ->setParameter('id', $id)
            ->executeStatement();
    }

    private function decryptToken(?string $value): string
    {
        try {
            return $value ? $this->decrypt($value) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function getExpireTime(array $tokens): string
    {
        return date('Y-m-d H:i:s', time() + (int)($tokens['expires_in'] ?? 0));
    }

    private function hashSessionId(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
