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
use Atro\Core\Exceptions\Forbidden;
use Atro\Entities\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies the credentials of a user by the authentication methods registered in the metadata
 * ("app.authentication.types"), e.g. the local password or LDAP. Every attempt is logged.
 */
class UserAuthenticator
{
    public function __construct(private readonly Container $container, private readonly SignInLog $signInLog)
    {
    }

    /**
     * @throws Forbidden When the IP address has made too many failed attempts.
     */
    public function authenticate(string $username, string $password, ServerRequestInterface $request): ?User
    {
        $this->signInLog->checkFailedAttemptsLimit((string)($request->getServerParams()['REMOTE_ADDR'] ?? ''));

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
