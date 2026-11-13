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

namespace Atro\Core\Authentication;

use Atro\Core\Container;
use Atro\Entities\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies the credentials of a user by the authentication methods registered in the metadata
 * ("app.authentication.types"), e.g. the local password or LDAP. Every attempt is logged; guessing is limited by the
 * rate limit of the sign-in route.
 */
class UserAuthenticator
{
    public function __construct(private readonly Container $container, private readonly SignInLog $signInLog)
    {
    }

    public function authenticate(string $username, string $password, ServerRequestInterface $request): ?User
    {
        $user = $this->findUser($username, $password);

        $denialReason = null;
        if ($user === null) {
            $denialReason = 'CREDENTIALS';
        } elseif (!$user->isActive()) {
            $denialReason = 'INACTIVE_USER';
        }

        $this->signInLog->log($username, $user, $denialReason, $request);

        return $denialReason === null ? $user : null;
    }

    private function findUser(string $username, string $password): ?User
    {
        foreach ($this->container->get('metadata')->get('app.authentication.types', []) as $className) {
            if (!is_a($className, AuthenticationMethodInterface::class, true)) {
                continue;
            }

            $user = $this->container->get($className)->authenticate($username, $password);
            if ($user instanceof User) {
                return $user;
            }
        }

        return null;
    }
}
