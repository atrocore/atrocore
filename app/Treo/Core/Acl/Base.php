<?php
declare(strict_types=1);

namespace Treo\Core\Acl;

use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * Class Base
 *
 * @author r.ratsun@gmail.com
 */
class Base extends \Espo\Core\Acl\Base
{
    /**
     * Init
     */
    protected function init()
    {
        $this->addDependency('metadata');
    }

    /**
     * Check is Owner param
     *
     * @param User   $user
     * @param Entity $entity
     *
     * @return bool
     */
    public function checkIsOwner(User $user, Entity $entity)
    {
        // prepare data
        $hasOwnerUser = $this
            ->getInjection('metadata')
            ->get('scopes.' . $entity->getEntityType() . '.hasOwner');
        $hasAssignedUser = $this
            ->getInjection('metadata')
            ->get('scopes.' . $entity->getEntityType() . '.hasAssignedUser');

        if ($hasOwnerUser) {
            if ($entity->has('ownerUserId')) {
                if ($user->id === $entity->get('ownerUserId')) {
                    return true;
                }
            }
        }

        if ($hasAssignedUser) {
            if ($entity->has('assignedUserId')) {
                if ($user->id === $entity->get('assignedUserId')) {
                    return true;
                }
            }
        }

        if ($entity->hasAttribute('createdById') && !$hasOwnerUser && !$hasAssignedUser) {
            if ($entity->has('createdById')) {
                if ($user->id === $entity->get('createdById')) {
                    return true;
                }
            }
        }

        if ($entity->hasAttribute('assignedUsersIds') && $entity->hasRelation('assignedUsers')) {
            if ($entity->hasLinkMultipleId('assignedUsers', $user->id)) {
                return true;
            }
        }

        return false;
    }
}
