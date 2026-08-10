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

namespace Atro\ConnectionType;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\DataManager;
use Espo\ORM\Entity;

class ConnectionOauth2 extends ConnectionHttp implements ConnectionInterface
{
    public function connect(Entity $connectionEntity)
    {
        $grantType = $connectionEntity->get('oauthGrantType');
        $dataManager = $this->getDataManager();
        $key = $this->getCacheKey();

        // Identity hash: invalidates the cache when credentials actually change,
        // independent of whether a given token was obtained via password or refresh_token grant.
        $hash = md5(implode('|', [
            $connectionEntity->get('oauthUrl'),
            $grantType,
            $connectionEntity->get('oauthClientId'),
            $connectionEntity->get('user'),
            (string) $this->decryptPassword((string) $connectionEntity->get('password')),
            (string) $this->decryptPassword((string) $connectionEntity->get('oauthClientSecret')),
        ]));

        $cached = $dataManager->getCacheData($key);
        if (!empty($cached['hash']) && $cached['hash'] === $hash) {
            if (!empty($cached['expires_at']) && time() < $cached['expires_at']) {
                return $cached;
            }
            if (!empty($cached['refresh_token'])) {
                $result = $this->attemptRefreshToken($connectionEntity, $cached['refresh_token'], $hash);
                if ($result !== null) {
                    return $result;
                }
                // null = refresh token specifically rejected (400/401) -> fall through to a fresh login
            }
        }

        $body = ['grant_type' => $grantType];
        switch ($grantType) {
            case 'client_credentials':
                $body['client_id'] = $connectionEntity->get('oauthClientId');
                $body['client_secret'] = $this->decryptPassword($connectionEntity->get('oauthClientSecret'));
                break;
            case 'password':
                $body['username'] = $connectionEntity->get('user');
                $body['password'] = $this->decryptPassword($connectionEntity->get('password'));
                break;
            default:
                throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
        }

        [, $result, $curlError] = $this->postToken($connectionEntity, $body, $grantType === 'password');
        if ($curlError !== '' || empty($result['access_token'])) {
            throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
        }

        return $this->finalizeToken($result, $hash, $key);
    }

    private function attemptRefreshToken(Entity $connectionEntity, string $refreshToken, string $hash): ?array
    {
        [$httpCode, $result, $curlError] = $this->postToken($connectionEntity, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ], true);

        if ($curlError !== '') {
            // network-level failure, not a credential problem - surface it, don't discard the refresh token
            throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
        }
        if (!empty($result['access_token'])) {
            return $this->finalizeToken($result, $hash, $this->getCacheKey());
        }
        if (in_array($httpCode, [400, 401], true)) {
            return null; // caller falls back to a fresh password-grant login
        }

        // unexpected status (5xx etc.) - a real error, not "refresh token invalid": don't silently fall back
        throw new BadRequest(sprintf($this->exception('connectionFailed'), 'Connection failed.'));
    }

    private function postToken(Entity $connectionEntity, array $body, bool $useBasicAuth): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $connectionEntity->get('oauthUrl'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($useBasicAuth && $connectionEntity->get('oauthClientId')) {
            curl_setopt($ch, CURLOPT_USERPWD, $connectionEntity->get('oauthClientId') . ':' . $this->decryptPassword($connectionEntity->get('oauthClientSecret')));
        }
        if ($connectionEntity->get('verifySsl') === false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // nosemgrep:php.lang.security.curl-ssl-verifypeer-off.curl-ssl-verifypeer-off
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [$httpCode, !empty($response) ? (@json_decode($response, true) ?? []) : [], $curlError];
    }

    private function finalizeToken(array $result, string $hash, string $key): array
    {
        if (!empty($result['expires_in'])) {
            $result['expires_at'] = time() + ((int) $result['expires_in']) - 60;
            $result['hash'] = $hash;
            $this->getDataManager()->setCacheData($key, $result);
        }

        return $result;
    }

    public function processError(int $httpCode, ?string $output)
    {
        if ($httpCode === 401) {
            $this->getDataManager()->removeCacheData($this->getCacheKey());
        }
        parent::processError($httpCode, $output);
    }

    public function getCacheKey(): string
    {
        return 'cron_conn_' . $this->connectionEntity->get('id');
    }

    public function getHeaders(): array
    {
        $connectionData = $this->connect($this->connectionEntity);
        $tokenType = ucfirst($connectionData['token_type']);

        return ["Authorization: {$tokenType} {$connectionData['access_token']}"];
    }

    public function getDataManager(): DataManager
    {
        return $this->container->get('dataManager');
    }
}
