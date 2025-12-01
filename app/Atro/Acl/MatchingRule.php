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

class MatchingRule extends Base
{
    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        return $this->getAclManager()->checkScope($user, 'Matching', $action);
    }

    public function checkEntity(User $user, Entity $entity, $data, $action)
    {
        if (!empty($entity->get('matchingId'))) {
            $matching = $this->getEntityManager()->getEntity('Matching', $entity->get('matchingId'));
        }

        if (empty($matching)) {
            return false;
        }

        if (in_array($action, ['create', 'delete'])) {
            $action = 'edit';
        }

        return $this->getAclManager()->checkEntity($user, $matching, $action);
    }
}
