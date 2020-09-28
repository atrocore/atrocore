<?php

declare(strict_types=1);

namespace Treo\Core\SelectManagers;

/**
 * Class Base
 *
 * @author r.ratsun r.ratsun@zinitsolutions.com
 */
class Base extends \Espo\Core\SelectManagers\Base
{
    /**
     * @param array $result
     */
    protected function accessOnlyOwn(&$result)
    {
        if ($this->hasAssignedUsersField()) {
            $this->setDistinct(true, $result);
            $this->addLeftJoin('assignedUsers', $result);
            $result['whereClause'][] = array(
                'assignedUsers.id' => $this->getUser()->id
            );
            return;
        }

        if ($this->hasOwnerUserField()) {
            $d['ownerUserId'] = $this->getUser()->id;
        }
        if ($this->hasAssignedUserField()) {
            $d['assignedUserId'] = $this->getUser()->id;
        }
        if ($this->hasCreatedByField() && !$this->hasAssignedUserField() && !$this->hasOwnerUserField()) {
            $d['createdById'] = $this->getUser()->id;
        }

        $result['whereClause'][] = array(
            'OR' => $d
        );
    }

    /**
     * @param array $result
     */
    protected function accessOnlyTeam(&$result)
    {
        if (!$this->hasTeamsField()) {
            return;
        }

        $this->setDistinct(true, $result);
        $this->addLeftJoin(['teams', 'teamsAccess'], $result);

        if ($this->hasAssignedUsersField()) {
            $this->addLeftJoin(['assignedUsers', 'assignedUsersAccess'], $result);
            $result['whereClause'][] = array(
                'OR' => array(
                    'teamsAccess.id'         => $this->getUser()->getLinkMultipleIdList('teams'),
                    'assignedUsersAccess.id' => $this->getUser()->id
                )
            );
            return;
        }

        $d = array(
            'teamsAccess.id' => $this->getUser()->getLinkMultipleIdList('teams')
        );

        if ($this->hasOwnerUserField()) {
            $d['ownerUserId'] = $this->getUser()->id;
        }

        if ($this->hasAssignedUserField()) {
            $d['assignedUserId'] = $this->getUser()->id;
        }

        if ($this->hasCreatedByField() && !$this->hasAssignedUserField() && !$this->hasOwnerUserField()) {
            $d['createdById'] = $this->getUser()->id;
        }

        $result['whereClause'][] = array(
            'OR' => $d
        );
    }

    /**
     * @return bool
     */
    protected function hasOwnerUserField()
    {
        if ($this->getMetadata()->get('scopes.' . $this->getEntityType() . '.hasOwner')) {
            return true;
        }
    }

    /**
     * @return bool
     */
    protected function hasAssignedUsersField()
    {
        if ($this->getMetadata()->get('scopes.' . $this->getEntityType() . '.hasAssignedUser')
            && $this->getSeed()->hasRelation('assignedUsers')
            && $this->getSeed()->hasAttribute('assignedUsersIds')) {
            return true;
        }
    }

    /**
     * @return bool
     */
    protected function hasAssignedUserField()
    {
        if ($this->getMetadata()->get('scopes.' . $this->getEntityType() . '.hasAssignedUser')) {
            return true;
        }
    }

    /**
     * OnlyActive filter
     *
     * @param array $result
     */
    protected function boolFilterOnlyActive(&$result)
    {
        $result['whereClause'][] = [
            'isActive' => true
        ];
    }
}
