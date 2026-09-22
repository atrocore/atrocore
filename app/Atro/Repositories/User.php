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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\ORM\Repositories\RDB;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\Entities\User as UserEntity;
use Doctrine\DBAL\ParameterType;
use Espo\Core\AclManager;
use Espo\Core\ServiceFactory;
use Espo\ORM\Entity;

class User extends RDB
{
    protected ?UserEntity $systemUser = null;

    public const string AVATAR_DIR = 'data/upload/avatars';

    public function getGlobalSystemUser(): UserEntity
    {
        if ($this->systemUser === null) {
            $this->systemUser = $this->where(['userName' => 'system'])->findOne();
            if (empty($this->systemUser)) {
                $id = IdGenerator::uuid();

                $user = $this->get();
                $user->id = $id;
                $user->set('actorId', $id);
                $user->set('delegatorId', $id);
                $user->set('userName', 'system');
                $user->set('lastName', 'System');
                $user->set('isActive', true);
                $user->set('followEntityOnStreamPost', false);
                $user->set('isAdmin', true);
                $user->set('type', 'System');
                $this->save($user);

                $this->systemUser = $user;
            }
        }

        if (empty($this->getConfig()->get('systemUserId')) || $this->getConfig()->get('systemUserId') !== $this->systemUser->id) {
            $this->getConfig()->set('systemUserId', $this->systemUser->id);
            $this->getConfig()->save();
        }

        return $this->systemUser;
    }

    public function getSystemUser(UserEntity $user): UserEntity
    {
        if ($user->get('type') === 'System') {
            return $user;
        }

        $globalSystemUserId = $this->getGlobalSystemUser()->id;

        $systemUser = $this
            ->where([
                'type'        => 'System',
                'actorId'     => $globalSystemUserId,
                'delegatorId' => $user->id
            ])
            ->findOne();

        if (empty($systemUser)) {
            $systemUser = $this->get();
            $systemUser->set('type', 'System');
            $systemUser->set('userName', IdGenerator::unsortableId());
            $systemUser->set('isActive', true);
            $systemUser->set('followEntityOnStreamPost', false);
            $systemUser->set('isAdmin', false);
            $systemUser->set('actorId', $globalSystemUserId);
            $systemUser->set('delegatorId', $user->id);
            $this->save($systemUser);
        }

        return $systemUser;
    }

    public function getAdminUsers(): array
    {
        return $this->getDbal()->createQueryBuilder()
            ->select('id')
            ->from($this->getDbal()->quoteIdentifier('user'))
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

            if (empty($entity->get('id'))) {
                $entity->set('id', self::generateId());
            }

            if (empty($entity->get('delegatorId'))) {
                $entity->set('delegatorId', $entity->get('id'));
            }

            if (empty($entity->get('actorId'))) {
                $entity->set('actorId', $entity->get('id'));
            }

        } else {
            if ($entity->isAttributeChanged('userName')) {
                $userName = $entity->get('userName');
                if (empty($userName)) {
                    throw new Error();
                }

                $user = $this->getEntityManager()->getRepository('User')
                    ->where([
                        'userName' => $userName,
                        'id!='     => $entity->id
                    ])
                    ->findOne();
                if ($user) {
                    throw new BadRequest($this->getLanguage()->translate('userNameExists', 'messages', 'User'));
                }
            }

            if ($entity->get('id') == 1 && !empty($this->getConfig()->get('demo'))) {
                if ($entity->isAttributeChanged('password') || $entity->isAttributeChanged('userName')) {
                    throw new BadRequest('Password change in the demo version is not possible.');
                }
            }

            $currentUser = $this->getEntityManager()->getUser();
            if ($currentUser !== null && !$currentUser->isAdmin()) {
                if ($entity->isAttributeChanged('isAdmin')) {
                    throw new Forbidden();
                }
                if ($entity->isAttributeChanged('isEntityAdmin')) {
                    throw new Forbidden();
                }
                if ($entity->isAttributeChanged('isRoleAdmin')) {
                    throw new Forbidden();
                }
                if ($entity->isAttributeChanged('isUserAdmin')) {
                    throw new Forbidden();
                }
                if ($entity->getFetched('isAdmin')) {
                    throw new Forbidden();
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

    public function uploadAvatar(UserEntity $user, string $contents, string $name, int $filesize): bool
    {
        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->getAllowedExtensions(), true)) {
            throw new BadRequest("Unsupported avatar file type.");
        }

        $maxSize = $this->convertToBytes((string)ini_get('upload_max_filesize'));
        if (!empty($maxSize) && $filesize > $maxSize) {
            throw new BadRequest("Avatar file exceeds the maximum upload size.");
        }

        $base64 = $contents;
        if (str_starts_with($base64, 'data:')) {
            $arr = explode(',', $base64);
            if (count($arr) > 1) {
                $base64 = $arr[1];
            }
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new BadRequest("Invalid avatar file data.");
        }

        $dir = $this->getAvatarDir($user);
        Util::removeDir($dir);
        Util::createDir($dir);

        try {
            file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $decoded);
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    public function deleteAvatar(UserEntity $user): bool
    {
        try {
            Util::removeDir($this->getAvatarDir($user));
        } catch (\Throwable $e) {
            throw new BadRequest("Error while deleting avatar file: " . $e->getMessage());
        }

        return true;
    }

    public function getAvatarName(UserEntity $user): ?string
    {
        if (!empty($path = $this->getAvatarFilePath($user))) {
            return basename($path);
        }

        return null;
    }

    public function getAvatarContent(UserEntity $user): ?string
    {
        if (!empty($path = $this->getAvatarFilePath($user))) {
            return file_get_contents($path);
        }

        return null;
    }

    public function getAvatarMimeType(UserEntity $user): ?string
    {
        if (!empty($path = $this->getAvatarFilePath($user))) {
            return mime_content_type($path);
        }

        return null;
    }

    protected function getAvatarDir(UserEntity $user): string
    {
        return self::AVATAR_DIR . '/' . $user->id;
    }

    protected function getAvatarFilePath(UserEntity $user): ?string
    {
        $dir = $this->getAvatarDir($user);

        if (is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $extension = strtolower((string)pathinfo($item, PATHINFO_EXTENSION));

                    if (in_array($extension, $this->getEntityManager()->getEspoMetadata()->get(['app', 'file', 'image', 'extensions'], []))) {
                        return $dir . DIRECTORY_SEPARATOR . $item;
                    }
                }
            }
        }

        return null;
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('teamsIds')
            || $entity->isAttributeChanged('rolesIds')
            || $entity->isAttributeChanged('isAdmin')
            || $entity->isAttributeChanged('isEntityAdmin')
            || $entity->isAttributeChanged('isRoleAdmin')
            || $entity->isAttributeChanged('isUserAdmin')) {
            $this
                ->getAclManager()
                ->clearAclCache();
        }

        if ($entity->isAttributeChanged('isActive')
            || $entity->isAttributeChanged('receiveNotifications')
            || $entity->isAttributeChanged('notificationProfileId')) {
            $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();
        }

        if ($entity->isAttributeChanged('localeId')
            || $entity->isAttributeChanged('styleId')
        ) {
            $this->getInjection('container')->get('dataManager')->clearCache(true);
        }

        if ($entity->isAttributeChanged('isActive') && empty($entity->get('isActive'))) {
            $this->getDbal()->createQueryBuilder()
                ->delete('auth_token')
                ->where('user_id = :userId')
                ->setParameter('userId', $entity->get('id'))
                ->executeQuery();
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();

        /* @var $entity UserEntity */
        $this->deleteAvatar($entity);
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        $this->getEntityManager()->getRepository('NotificationRule')->deleteCacheFile();
    }

    protected function getAllowedExtensions(): array
    {
        return $this->getMetadata()->get(['app', 'file', 'image', 'extensions'], []);
    }

    protected function convertToBytes(string $size): int
    {
        $suffix = substr($size, -1);
        $value = (int)substr($size, 0, -1);

        switch (strtoupper($suffix)) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
                break;
        }

        return $value;
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getInjection('container')->get('serviceFactory');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
