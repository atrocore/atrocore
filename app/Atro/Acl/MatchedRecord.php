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

use Atro\Entities\User;
use Espo\Core\Acl\Base;
use Espo\ORM\Entity;

class MatchedRecord extends Base
{
    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        $sourceEntity = $this
            ->getEntityManager()
            ->getRepository($entity->get('sourceEntity'))
            ->get($entity->get('sourceEntityId'));

        if (empty($sourceEntity)) {
            return false;
        }

        $masterEntity = $this
            ->getEntityManager()
            ->getRepository($entity->get('masterEntity'))
            ->get($entity->get('masterEntityId'));

        if (empty($masterEntity)) {
            return false;
        }

        return
            $this->checkEntity($user, $entity, $data, 'read')
            && $this->getAclManager()->checkEntity($user, $sourceEntity, 'read')
            && $this->getAclManager()->checkEntity($user, $masterEntity, 'read');
    }

    public function checkEntityEdit(User $user, Entity $entity, $data)
    {
        return $this->checkEntityRead($user, $entity, $data) && parent::checkEntityEdit($user, $entity, $data);
    }

    public function checkEntityDelete(User $user, Entity $entity, $data)
    {
        return $this->checkEntityRead($user, $entity, $data) && parent::checkEntityDelete($user, $entity, $data);
    }
}

