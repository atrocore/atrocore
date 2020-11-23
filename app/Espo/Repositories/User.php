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
 */

namespace Espo\Repositories;

use Espo\Core\AclManager;
use \Espo\ORM\Entity;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Conflict;

class User extends \Espo\Core\ORM\Repositories\RDB
{
    protected function init()
    {
        parent::init();
        $this->addDependency('container');
    }

    protected function beforeSave(Entity $entity, array $options = array())
    {
        parent::beforeSave($entity, $options);

        if ($entity->isNew()) {
            $userName = $entity->get('userName');
            if (empty($userName)) {
                throw new Error();
            }

            $user = $this->where(array(
                'userName' => $userName
            ))->findOne();

            if ($user) {
                throw new Conflict(json_encode(['reason' => 'userNameExists']));
            }
        } else {
            if ($entity->isAttributeChanged('userName')) {
                $userName = $entity->get('userName');
                if (empty($userName)) {
                    throw new Error();
                }

                $user = $this->where(array(
                    'userName' => $userName,
                    'id!=' => $entity->id
                ))->findOne();
                if ($user) {
                    throw new Conflict(json_encode(['reason' => 'userNameExists']));
                }
            }
        }

        if ($entity->has('isAdmin') && $entity->get('isAdmin')) {
            $entity->set('isPortalUser', false);
            $entity->set('portalRolesIds', []);
            $entity->set('portalRolesNames', (object)[]);
            $entity->set('portalsIds', []);
            $entity->set('portalsNames', (object)[]);
        }

        if ($entity->has('isPortalUser') && $entity->get('isPortalUser')) {
            $entity->set('isAdmin', false);
            $entity->set('rolesIds', []);
            $entity->set('rolesNames', (object)[]);
            $entity->set('teamsIds', []);
            $entity->set('teamsNames', (object)[]);
            $entity->set('defaultTeamId', null);
            $entity->set('defaultTeamName', null);
        }
    }

    public function checkBelongsToAnyOfTeams($userId, array $teamIds)
    {
        if (empty($teamIds)) {
            return false;
        }

        $pdo = $this->getEntityManager()->getPDO();

        $arr = [];
        foreach ($teamIds as $teamId) {
            $arr[] = $pdo->quote($teamId);
        }

        $sql = "SELECT * FROM team_user WHERE deleted = 0 AND user_id = :userId AND team_id IN (".implode(", ", $arr).")";

        $sth = $pdo->prepare($sql);
        $sth->execute(array(
            ':userId' => $userId
        ));
        if ($row = $sth->fetch()) {
            return true;
        }
        return false;
    }

    /**
     * @param Entity $entity
     *
     * @param array $options
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('teamsIds')
            || $entity->isAttributeChanged('rolesIds')
            || $entity->isAttributeChanged('isAdmin')) {
            $this
                ->getAclManager()
                ->clearAclCache();
        }
    }

    /**
     * @return AclManager
     */
    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }
}
