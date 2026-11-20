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
use Atro\Core\OAuth\Grants\AuthCodeGrant;
use Atro\Core\OAuth\Grants\ClientCredentialsGrant;
use Atro\Core\OAuth\Grants\RefreshTokenGrant;
use Atro\Core\OAuth\Keys;
use Atro\Core\OAuth\Repositories\AccessTokenRepository;
use Atro\Core\OAuth\Repositories\AuthCodeRepository;
use Atro\Core\OAuth\Repositories\ClientRepository;
use Atro\Core\OAuth\Repositories\RefreshTokenRepository;
use Atro\Core\OAuth\Repositories\ScopeRepository;
use League\OAuth2\Server\AuthorizationServer;

/**
 * The OAuth authorization server. The access and refresh tokens get the lifetimes of their client (OauthClient);
 * an authorization code is valid for a fixed time.
 */
class OauthAuthorizationServer implements FactoryInterface
{
    public const AUTH_CODE_TTL = 'PT10M';

    public function create(Container $container)
    {
        $keys = $container->get(Keys::class);

        $server = new AuthorizationServer(
            $container->get(ClientRepository::class),
            $container->get(AccessTokenRepository::class),
            $container->get(ScopeRepository::class),
            $keys->getPrivateKey(),
            $keys->getEncryptionKey()
        );

        $server->enableGrantType(new ClientCredentialsGrant());

        $authCodeGrant = new AuthCodeGrant(
            $container->get(AuthCodeRepository::class),
            $container->get(RefreshTokenRepository::class),
            new \DateInterval(self::AUTH_CODE_TTL)
        );
        // PKCE is required from every client and validated by Atro\Core\OAuth\Authorization
        $authCodeGrant->disableRequireCodeChallengeForPublicClients();
        $server->enableGrantType($authCodeGrant);

        $server->enableGrantType(new RefreshTokenGrant($container->get(RefreshTokenRepository::class)));

        return $server;
    }
}
