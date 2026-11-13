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

use Atro\Entities\User;

/**
 * A way to verify the username and the password of a user, e.g. the local password or LDAP. The methods are registered
 * in the metadata "app.authentication.types" and are tried one after another.
 */
interface AuthenticationMethodInterface
{
    /**
     * The user with these credentials, null when they do not match.
     */
    public function authenticate(string $username, string $password): ?User;
}
