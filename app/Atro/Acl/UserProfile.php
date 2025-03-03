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

class UserProfile extends Base
{
    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = [])
    {
        return true;
    }

    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        return $user->id === $entity->id;
    }

    public function checkIsOwner(User $user, Entity $entity)
    {
        return $user->id === $entity->id;
    }
}
