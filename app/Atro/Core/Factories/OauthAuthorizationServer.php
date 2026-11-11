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

namespace Atro\Core\Factories;

use Atro\Core\Container;
use Atro\Core\OAuth\Keys;
use Atro\Core\OAuth\Repositories\AccessTokenRepository;
use Atro\Core\OAuth\Repositories\ClientRepository;
use Atro\Core\OAuth\Repositories\RefreshTokenRepository;
use Atro\Core\OAuth\Repositories\ScopeRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;

class OauthAuthorizationServer implements FactoryInterface
{
    public const ACCESS_TOKEN_TTL_CONFIG_KEY = 'oauthAccessTokenTtl';
    public const REFRESH_TOKEN_TTL_CONFIG_KEY = 'oauthRefreshTokenTtl';

    public const DEFAULT_ACCESS_TOKEN_TTL = 'PT1H';
    public const DEFAULT_REFRESH_TOKEN_TTL = 'P1M';

    public function create(Container $container)
    {
        $config = $container->get('config');
        $keys = $container->get(Keys::class);

        $server = new AuthorizationServer(
            $container->get(ClientRepository::class),
            $container->get(AccessTokenRepository::class),
            $container->get(ScopeRepository::class),
            $keys->getPrivateKey(),
            $keys->getEncryptionKey()
        );

        $accessTokenTtl = new \DateInterval($config->get(self::ACCESS_TOKEN_TTL_CONFIG_KEY, self::DEFAULT_ACCESS_TOKEN_TTL));

        $server->enableGrantType(new ClientCredentialsGrant(), $accessTokenTtl);

        $refreshTokenGrant = new RefreshTokenGrant($container->get(RefreshTokenRepository::class));
        $refreshTokenGrant->setRefreshTokenTTL(new \DateInterval($config->get(self::REFRESH_TOKEN_TTL_CONFIG_KEY, self::DEFAULT_REFRESH_TOKEN_TTL)));
        $server->enableGrantType($refreshTokenGrant, $accessTokenTtl);

        return $server;
    }
}
