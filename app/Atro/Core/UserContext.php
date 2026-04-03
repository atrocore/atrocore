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

namespace Atro\Core;

use Atro\Entities\User;
use Espo\Core\Acl;
use Espo\Core\AclManager;

/**
 * Holds the currently authenticated user for the duration of the request or job.
 * Register the current user via set(); retrieve it via getUser().
 */
class UserContext
{
    private ?User $user = null;
    private ?Acl $acl = null;

    public function set(User $user): void
    {
        $this->user = $user;
        $this->acl = null;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getAcl(AclManager $aclManager): Acl
    {
        if ($this->acl === null) {
            $this->acl = new Acl($aclManager, $this->user);
        }

        return $this->acl;
    }
}
