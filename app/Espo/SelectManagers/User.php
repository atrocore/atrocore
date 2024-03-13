<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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
 */

namespace Espo\SelectManagers;

class User extends \Espo\Core\SelectManagers\Base
{
    protected function access(&$result)
    {
        parent::access($result);

        if (!$this->getUser()->isAdmin()) {
            $result['whereClause'][] = array(
                'isActive' => true
            );
        }
        $result['whereClause'][] = array(
            'isSuperAdmin' => false
        );
    }

    protected function filterActive(&$result)
    {
        $result['whereClause'][] = [
            'isActive' => true
        ];
    }

    protected function boolFilterOnlyMyTeam(&$result)
    {
        $this->addJoin(['teams', 'teamsMiddle'], $result);
        $result['whereClause'][] = array(
        	'teamsMiddle.teamId' => $this->getUser()->getLinkMultipleIdList('teams')
        );
        $this->setDistinct(true, $result);
    }

    protected function accessOnlyOwn(&$result)
    {
        $result['whereClause'][] = array(
            'id' => $this->getUser()->id
        );
    }

    protected function accessOnlyTeam(&$result)
    {
        $this->setDistinct(true, $result);
        $this->addLeftJoin(['teams', 'teamsAccess'], $result);
        $result['whereClause'][] = array(
            'OR' => array(
                'teamsAccess.id' => $this->getUser()->getLinkMultipleIdList('teams'),
                'id' => $this->getUser()->id
            )
        );
    }
}

