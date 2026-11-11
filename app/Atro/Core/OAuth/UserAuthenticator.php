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
use Atro\Entities\User;
use Espo\Core\Utils\Auth;
use Espo\Core\Utils\Authentication\AbstractAuthentication;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies the credentials of a user by the authentication types registered in the metadata ("app.authentication.types"),
 * e.g. a local password or LDAP.
 */
class UserAuthenticator
{
    public function __construct(private readonly Container $container)
    {
    }

    public function authenticate(string $username, string $password, ServerRequestInterface $request): ?User
    {
        $auth = new Auth($this->container, false, $request);

        foreach (array_reverse($this->container->get('metadata')->get('app.authentication.types', [])) as $className) {
            if (!is_a($className, AbstractAuthentication::class, true)) {
                continue;
            }

            $user = (new $className($auth, $this->container))->login($username, $password);
            if ($user instanceof User) {
                return $user->isActive() ? $user : null;
            }
        }

        return null;
    }
}
