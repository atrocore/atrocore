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

namespace Atro\Acl;

use Atro\Entities\User;
use Espo\Core\Acl\Base;
use Espo\ORM\Entity;

/**
 * The OAuth clients are managed by administrators only: a client acts with the permissions of its user and its secret
 * gives access on behalf of that user.
 */
class OauthClient extends Base
{
    public function checkScope(User $user, $data, $action = null, ?Entity $entity = null, $entityAccessData = array())
    {
        return $user->isAdmin();
    }

    public function checkEntityCreate(User $user, Entity $entity, $data)
    {
        return $user->isAdmin();
    }

    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        return $user->isAdmin();
    }

    public function checkEntityEdit(User $user, Entity $entity, $data)
    {
        return $user->isAdmin();
    }

    public function checkEntityDelete(User $user, Entity $entity, $data)
    {
        return $user->isAdmin();
    }
}
