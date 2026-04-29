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
use Atro\Entities\User as UserEntity;
use Espo\ORM\Entity;

class User extends Base
{
    public function checkEntityRead(UserEntity $user, Entity $entity, $data)
    {
        if (!$user->isAdmin() && $entity->get('isAdmin')) {
            return false;
        }

        return parent::checkEntityRead($user, $entity, $data);
    }

    public function checkEntityEdit(UserEntity $user, Entity $entity, $data)
    {
        if (!$user->isAdmin() && $entity->get('isAdmin')) {
            return false;
        }

        return parent::checkEntityEdit($user, $entity, $data);
    }

    public function checkEntityDelete(UserEntity $user, Entity $entity, $data)
    {
        if (!$user->isAdmin() && $entity->get('isAdmin')) {
            return false;
        }

        return parent::checkEntityDelete($user, $entity, $data);
    }
}
