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

use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;

/**
 * Reads the data of a token issued by the OAuth server. A token that is invalid, expired or revoked has no data.
 */
class TokenReader
{
    use CryptTrait;

    public function __construct(private readonly ResourceServer $resourceServer, Keys $keys)
    {
        $this->setEncryptionKey($keys->getEncryptionKey());
    }

    /**
     * An access token is a JWT: it is read by the resource server, which also verifies its signature.
     *
     * @return array{identifier: string, clientId: string, userId: string}|null
     */
    public function readAccessToken(string $token): ?array
    {
        $request = (new ServerRequest('POST', '/'))->withHeader('Authorization', 'Bearer ' . $token);

        try {
            $request = $this->resourceServer->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $e) {
            return null;
        }

        return [
            'identifier' => (string)$request->getAttribute('oauth_access_token_id'),
            'clientId'   => (string)$request->getAttribute('oauth_client_id'),
            'userId'     => (string)$request->getAttribute('oauth_user_id'),
        ];
    }

    /**
     * A refresh token is the payload encrypted by the authorization server.
     *
     * @return array{identifier: string, accessTokenIdentifier: string, clientId: string}|null
     */
    public function readRefreshToken(string $token): ?array
    {
        try {
            $data = json_decode($this->decrypt($token), true);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($data) || empty($data['refresh_token_id']) || ($data['expire_time'] ?? 0) < time()) {
            return null;
        }

        return [
            'identifier'            => (string)$data['refresh_token_id'],
            'accessTokenIdentifier' => (string)($data['access_token_id'] ?? ''),
            'clientId'              => (string)($data['client_id'] ?? ''),
        ];
    }
}
