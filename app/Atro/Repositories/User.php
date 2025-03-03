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

namespace Atro\Repositories;

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\ORM\Repositories\RDB;
use Doctrine\DBAL\ParameterType;
use Espo\Core\AclManager;
use Espo\ORM\Entity;

class User extends RDB
{
    public function getAdminUsers(): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->getConnection()->quoteIdentifier('user'))
            ->where('deleted = :false')
            ->andWhere('is_admin = :true')
            ->andWhere('is_active = :true')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->fetchAllAssociative();
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
                    'id!='     => $entity->id
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

        $sql = "SELECT * FROM team_user WHERE deleted = :deleted AND user_id = :userId AND team_id IN (" . implode(", ", $arr) . ")";

        $sth = $pdo->prepare($sql);
        $sth->bindValue(':deleted', false, \PDO::PARAM_BOOL);
        $sth->bindValue(':userId', $userId);
        $sth->execute();
        if ($sth->fetch()) {
            return true;
        }
        return false;
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('teamsIds')
            || $entity->isAttributeChanged('rolesIds')
            || $entity->isAttributeChanged('isAdmin')) {
            $this
                ->getAclManager()
                ->clearAclCache();
        }

        if ($entity->isAttributeChanged('isActive')) {
            $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();
        }

        if ($entity->isAttributeChanged('localeId')
            || $entity->isAttributeChanged('styleId')
            || $entity->isAttributeChanged('avatarId')
        ) {
            $this->getInjection('container')->get('dataManager')->clearCache(true);
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
