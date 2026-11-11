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
    public function checkScope(\Atro\Entities\User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        return $this->getAclManager()->checkScope($user, 'Matching', $action);
    }

    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        $originalRecord = $this
            ->getEntityManager()
            ->getRepository($entity->get('originalEntity'))
            ->get($entity->get('originalEntityId'));

        if (empty($originalRecord)) {
            return false;
        }

        $matchedRecord = $this
            ->getEntityManager()
            ->getRepository($entity->get('matchedEntity'))
            ->get($entity->get('matchedEntityId'));

        if (empty($matchedRecord)) {
            return false;
        }

        return
            $this->checkEntity($user, $entity, $data, 'read')
            && $this->getAclManager()->checkEntity($user, $originalRecord, 'read')
            && $this->getAclManager()->checkEntity($user, $matchedRecord, 'read');
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

