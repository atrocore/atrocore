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

namespace Atro\Acl;

use Espo\Core\Acl\Base;
use Atro\Entities\User;
use Espo\ORM\Entity;

class Role extends Base
{
    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        if ($user->isAdmin() || $user->isRoleAdmin()) {
            return true;
        }

        return parent::checkScope($user, $data, $action, $entity, $entityAccessData);
    }

    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        return $user->isAdmin() || $user->isRoleAdmin();
    }

    public function checkEntityCreate(User $user, Entity $entity, $data)
    {
        return $user->isAdmin() || $user->isRoleAdmin();
    }

    public function checkEntityEdit(User $user, Entity $entity, $data)
    {
        return $user->isAdmin() || $user->isRoleAdmin();
    }

    public function checkEntityDelete(User $user, Entity $entity, $data)
    {
        return $user->isAdmin() || $user->isRoleAdmin();
    }
}
