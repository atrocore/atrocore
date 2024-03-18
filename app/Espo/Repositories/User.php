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

namespace Espo\Repositories;

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\BadRequest;
use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\AclManager;
use Espo\ORM\Entity;

class User extends \Espo\Core\ORM\Repositories\RDB
{
    /**
     * Get admin users
     *
     * @return array
     */
    public function getAdminUsers(): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('u.id, p.data')
            ->from($this->getConnection()->quoteIdentifier('user'), 'u')
            ->leftJoin('u', $this->getConnection()->quoteIdentifier('preferences'), 'p', 'u.id = p.id')
            ->where('u.deleted = :false')
            ->andWhere('u.is_admin = :true')
            ->andWhere('u.is_active = :true')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('true', true, Mapper::getParameterType(true))
            ->fetchAllAssociative();
    }

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
                throw new BadRequest($this->getLanguage()->translate('userNameExists', 'messages', 'User'));
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
                    throw new BadRequest($this->getLanguage()->translate('userNameExists', 'messages', 'User'));
                }
            }

            if ($entity->get('id') == 1 && !empty($this->getConfig()->get('demo'))) {
                if ($entity->isAttributeChanged('password') || $entity->isAttributeChanged('userName')) {
                    throw new BadRequest('Password change in the demo version is not possible.');
                }
            }
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

        $sql = "SELECT * FROM team_user WHERE deleted = :deleted AND user_id = :userId AND team_id IN (".implode(", ", $arr).")";

        $sth = $pdo->prepare($sql);
        $sth->bindValue(':deleted', false, \PDO::PARAM_BOOL);
        $sth->bindValue(':userId', $userId);
        $sth->execute();
        if ($sth->fetch()) {
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

        if ($entity->isAttributeChanged('avatarId')) {
            $this->getConfig()->set('cacheTimestamp', time());
            $this->getConfig()->save();
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
