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

class Matching extends Base
{
    public function checkEntity(User $user, Entity $entity, $data, $action)
    {
        if (in_array($action, ['create', 'delete'])) {
            return false;
        }

        return parent::checkEntity($user, $entity, $data, $action);
    }
}
