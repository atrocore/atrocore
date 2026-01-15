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

class ClusterItem extends Base
{
    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        return $this->getAclManager()->checkScope($user, 'Cluster', $action);
    }

    public function checkEntity(User $user, Entity $entity, $data, $action)
    {
        if (!empty($entity->get('clusterId'))) {
            $cluster = $this->getEntityManager()->getEntity('Cluster', $entity->get('clusterId'));
        }

        if (empty($cluster)) {
            return false;
        }

        if ($action === 'read') {
            $record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('entityId'));
            if (empty($record)) {
                return false;
            }

            if (!$this->getAclManager()->checkEntity($user, $record, $action)) {
                return false;
            }
        }

        if (in_array($action, ['create', 'delete'])) {
            $action = 'edit';
        }

        return $this->getAclManager()->checkEntity($user, $cluster, $action);
    }
}
