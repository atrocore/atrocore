<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Treo\Core\SelectManagers;

/**
 * Class Base
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
