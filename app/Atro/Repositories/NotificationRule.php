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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\AclManager;
use Espo\Core\DataManager;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class NotificationRule extends Base
{
    const CACHE_NAME = 'notification_rule';

    public function findOneFromCache(string $notificationProfileId, string $occurrence, string $entityType): ?\Atro\Entities\NotificationRule
    {
        if (!$this->getDataManager()->isUseCache(self::CACHE_NAME)) {
            $rule = $this->where([
                "notificationProfileId" => $notificationProfileId,
                "occurrence" => $occurrence,
                "entity=" => $entityType
            ])->findOne();

            if(empty($rule)){
                return null;
            }

            $users[$notificationProfileId] = $this->getNotificationProfileUsers($notificationProfileId);
        } else {
            $cachedData = $this->getDataManager()->getCacheData(self::CACHE_NAME);

            if (empty($cachedData['notificationRules']) || empty($cachedData['users'])) {
                return null;
            }

            $notificationRules = $cachedData['notificationRules'];
            $users = $cachedData['users'];

            $result = array_filter($notificationRules, function ($rule) use ($notificationProfileId, $entityType, $occurrence) {
                return $rule['notification_profile_id'] === $notificationProfileId
                    && $rule['occurrence'] === $occurrence
                    && $rule['entity'] === $entityType;
            });

            if (empty($result)) {
                return null;
            }

            $result = array_values($result);
            $rule = $this->get();
            $rule->set(Util::arrayKeysToCamelCase($result[0]));
        }

        if (empty($users[$notificationProfileId])) {
            return null;
        }

        $receiverUsers = [];

        foreach ($users[$notificationProfileId] as $userArr) {
            $user = $this->getEntityManager()->getEntity('User');
            $user->set(Util::arrayKeysToCamelCase($userArr));
            $user->setAsFetched();

            if ($entityType === 'Note' && !$this->getAclManager()->checkScope($user, 'User', 'stream')) {
                continue;
            }

            if ($entityType !== '' && !$this->getAclManager()->checkScope($user, $entityType, 'read')) {
                continue;
            }
            $receiverUsers[] = $user;
        }

        if (empty($receiverUsers)) {
            return null;
        }

        $rule->receiverUsers = $receiverUsers;

        return $rule;
    }

    public function getNotificationProfileUsers($notificationProfileId): array
    {
        $connection = $this->getConnection();

        if (Converter::isPgSQL($connection)) {
            $subquery = "SELECT id FROM preferences WHERE (CAST(data::json->>'receiveNotifications' AS boolean) = :true) AND (data::json->>'notificationProfileId' = :notificationProfileId)";
        } else {
            $subquery = "SELECT id FROM preferences WHERE CAST(JSON_EXTRACT(data, '$.receiveNotifications') as UNSIGNED)= :true AND JSON_EXTRACT(data, '$.notificationProfileId') = :notificationProfileId";
        }

        $profileParam = $this->getConfig()->get('defaultNotificationProfileId') === $notificationProfileId
            ? 'default'
            : $notificationProfileId;

        $users = $connection->createQueryBuilder()
            ->select('u.id, u.name, u.user_name, u.first_name, u.last_name, u.email_address, u.phone_number')
            ->from($connection->quoteIdentifier('user'), 'u')
            ->where("u.id IN ($subquery)")
            ->andWhere('u.is_active = :true')
            ->andWhere('u.deleted = :false')
            ->andWhere('u.id <> :system')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('system', 'system')
            ->setParameter('notificationProfileId', $profileParam)
            ->fetchAllAssociative();

        $teamUsers = $connection->createQueryBuilder()
            ->select('tu.team_id, tu.user_id')
            ->from($connection->quoteIdentifier('team_user'), 'tu')
            ->where("tu.user_id IN (:users)")
            ->setParameter('users', array_column($users, 'id'), Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        foreach ($users as $userKey => $userArr) {
            $users[$userKey]['teamsIds'] = array_values(array_map(
                fn($item) => $item['team_id'],
                array_filter(
                    $teamUsers,
                    fn($item) => $item['user_id'] === $userArr['id']
                )
            ));
        }

        return $users;
    }

    public function deleteCacheFile(): void
    {
        if (empty($this->getMemoryStorage()->get('importJobId'))) {
            $file = DataManager::CACHE_DIR_PATH . '/' . self::CACHE_NAME . '.json';
            if (file_exists($file)) {
                unlink($file);
            }

            $this->getConfig()->remove('cacheTimestamp');
            $this->getConfig()->save();

            DataManager::pushPublicData('dataTimestamp', (new \DateTime())->getTimestamp());
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->deleteCacheFile();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('isActive')) {
            $this->deleteCacheFile();
        }

        parent::afterRemove($entity, $options);
    }

    protected function afterRestore($entity)
    {
        if ($entity->get('isActive')) {
            $this->deleteCacheFile();
        }

        parent::afterRestore($entity);
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('aclManager');
    }

    protected function init()
    {
        parent::init();
        $this->addDependency('dataManager');
        $this->addDependency('aclManager');
    }
}
