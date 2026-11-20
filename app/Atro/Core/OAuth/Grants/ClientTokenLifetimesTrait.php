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

namespace Atro\Core\OAuth\Grants;

use Atro\Core\OAuth\Entities\ClientEntity;
use DateInterval;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

/**
 * Issues the tokens with the lifetimes of the OAuth client (OauthClient: access and refresh token lifetime) instead of
 * one lifetime per grant type.
 */
trait ClientTokenLifetimesTrait
{
    protected function issueAccessToken(
        DateInterval $accessTokenTTL,
        ClientEntityInterface $client,
        string|null $userIdentifier,
        array $scopes = []
    ): AccessTokenEntityInterface {
        if ($client instanceof ClientEntity) {
            $accessTokenTTL = new DateInterval('PT' . $client->getAccessTokenLifetime() . 'M');
        }

        return parent::issueAccessToken($accessTokenTTL, $client, $userIdentifier, $scopes);
    }

    protected function issueRefreshToken(AccessTokenEntityInterface $accessToken): ?RefreshTokenEntityInterface
    {
        $client = $accessToken->getClient();
        if ($client instanceof ClientEntity) {
            $this->refreshTokenTTL = new DateInterval('PT' . $client->getRefreshTokenLifetime() . 'M');
        }

        return parent::issueRefreshToken($accessToken);
    }
}
