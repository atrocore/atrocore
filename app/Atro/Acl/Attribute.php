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

use Atro\Entities\User as EntityUser;
use Espo\Core\Acl\Base;
use Espo\ORM\Entity;

class Attribute extends Base
{
    public function checkEntityRead(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!empty($entity->get('attributeTabId')) && !empty($attributeTab = $entity->get('attributeTab'))) {
            if (!$this->getAclManager()->checkEntity($user, $attributeTab, 'read')) {
                return false;
            }
        }

        return $this->checkEntity($user, $entity, $data, 'read');
    }
}

