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
use Espo\Entities\User;
use Espo\ORM\Entity;

class SavedSearch extends Base
{
    public function checkIsOwner(User $user, Entity $entity)
    {
        return $user->id === $entity->get('userId');
    }

    public function checkScope(\Espo\Entities\User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        return true;
    }

    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        return true;
    }

    public function checkEntityCreate(User $user, Entity $entity, $data)
    {
        return true;
    }

    public function checkEntityEdit(User $user, Entity $entity, $data)
    {
        return $user->id === $entity->get('userId') || $user->isAdmin();
    }

    public function checkEntityDelete(User $user, Entity $entity, $data)
    {
        return $user->id === $entity->get('userId') || $user->isAdmin();
    }
}
