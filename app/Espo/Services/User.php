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

namespace Espo\Services;

use Espo\Core\Application as App;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Espo\Core\Utils\Util;

use \Espo\ORM\Entity;

class User extends Record
{
    const PASSWORD_CHANGE_REQUEST_LIFETIME = 360; // minutes

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected $internalAttributeList = ['password'];

    protected $nonAdminReadOnlyAttributeList = [
        'userName',
        'isActive',
        'isAdmin',
        'teamsIds',
        'teamsColumns',
        'teamsNames',
        'rolesIds',
        'rolesNames',
        'password',
        'accountId'
    ];

    protected $mandatorySelectAttributeList = [
        'isActive',
        'userName',
        'isAdmin'
    ];

    protected $linkSelectParams = array(
        'targetLists' => array(
            'additionalColumns' => array(
                'optedOut' => 'isOptedOut'
            )
        )
    );

    protected function getMailSender()
    {
        return $this->getContainer()->get('mailSender');
    }

    protected function getLanguage()
    {
        return $this->getContainer()->get('language');
    }

    protected function getFileManager()
    {
        return $this->getContainer()->get('fileManager');
    }

    protected function getContainer()
    {
        return $this->injections['container'];
    }

    public function getEntity($id = null)
    {
        if (isset($id) && $id == 'system') {
            throw new Forbidden();
        }

        $entity = parent::getEntity($id);
        if ($entity && $entity->get('isSuperAdmin') && !$this->getUser()->get('isSuperAdmin')) {
            throw new Forbidden();
        }
        return $entity;
    }

    public function findEntities($params)
    {
        if (empty($params['where'])) {
            $params['where'] = array();
        }
        $params['where'][] = array(
            'type' => 'notEquals',
            'field' => 'id',
            'value' => 'system'
        );

        $result = parent::findEntities($params);
        return $result;
    }

    public function changePassword($userId, $password, $checkCurrentPassword = false, $currentPassword = null)
    {
        $user = $this->getEntityManager()->getEntity('User', $userId);
        if (!$user) {
            throw new NotFound();
        }

        if ($user->get('isSuperAdmin') && !$this->getUser()->get('isSuperAdmin')) {
            throw new Forbidden();
        }

        if (empty($password)) {
            throw new Error('Password can\'t be empty.');
        }

        if ($checkCurrentPassword) {
            $passwordHash = new \Espo\Core\Utils\PasswordHash($this->getConfig());
            $u = $this->getEntityManager()->getRepository('User')->where(array(
                'id' => $user->id,
                'password' => $passwordHash->hash($currentPassword)
            ))->findOne();
            if (!$u) {
                throw new Forbidden();
            }
        }

        $user->set('password', $this->hashPassword($password));

        $this->getEntityManager()->saveEntity($user);

        return true;
    }

    public function passwordChangeRequest($userName, $emailAddress, $url = null)
    {
        $user = $this->getEntityManager()->getRepository('User')->where(array(
            'userName' => $userName,
            'emailAddress' => $emailAddress
        ))->findOne();

        if (empty($user)) {
            throw new NotFound();
        }

        if (!$user->isActive()) {
            throw new NotFound();
        }

        $userId = $user->id;

        $passwordChangeRequest = $this->getEntityManager()->getRepository('PasswordChangeRequest')->where(array(
            'userId' => $userId
        ))->findOne();
        if ($passwordChangeRequest) {
            throw new Forbidden();
        }

        $requestId = Util::generateId();

        $passwordChangeRequest = $this->getEntityManager()->getEntity('PasswordChangeRequest');
        $passwordChangeRequest->set(array(
            'userId' => $userId,
            'requestId' => $requestId,
            'url' => $url
        ));

        $this->sendChangePasswordLink($requestId, $emailAddress);

        $this->getEntityManager()->saveEntity($passwordChangeRequest);

        if (!$passwordChangeRequest->id) {
            throw new Error();
        }

        $dt = new \DateTime();
        $dt->add(new \DateInterval('PT'. self::PASSWORD_CHANGE_REQUEST_LIFETIME . 'M'));

        $job = $this->getEntityManager()->getEntity('Job');

        $job->set(array(
            'serviceName' => 'User',
            'methodName' => 'removeChangePasswordRequestJob',
            'data' => [
                'id' => $passwordChangeRequest->id
            ],
            'executeTime' => $dt->format('Y-m-d H:i:s')
        ));

        $this->getEntityManager()->saveEntity($job);

        return true;
    }

    public function removeChangePasswordRequestJob($data)
    {
        if (empty($data->id)) {
            return;
        }
        $id = $data->id;

        $p = $this->getEntityManager()->getEntity('PasswordChangeRequest', $id);
        if ($p) {
            $this->getEntityManager()->removeEntity($p);
        }
        return true;
    }

    protected function hashPassword($password)
    {
        $config = $this->getConfig();
        $passwordHash = new \Espo\Core\Utils\PasswordHash($config);

        return $passwordHash->hash($password);
    }

    protected function filterInput($data, string $id = null)
    {
        parent::filterInput($data);

        if (!$this->getUser()->get('isSuperAdmin')) {
            unset($data->isSuperAdmin);
        }

        if (!$this->getUser()->isAdmin()) {
            foreach ($this->nonAdminReadOnlyAttributeList as $attribute) {
                unset($data->$attribute);
            }
            if (!$this->getAcl()->checkScope('Team')) {
                unset($data->defaultTeamId);
            }
        }
    }

    public function createEntity($attachment)
    {
        $newPassword = null;
        if (property_exists($attachment, 'password')) {
            $newPassword = $attachment->password;
            $attachment->password = $this->hashPassword($attachment->password);
        }

        $user = parent::createEntity($attachment);

        if (!is_null($newPassword) && !empty($attachment->sendAccessInfo)) {
            if ($user->isActive()) {
                try {
                    $this->sendPassword($user, $newPassword);
                } catch (\Exception $e) {}
            }
        }

        return $user;
    }

    public function updateEntity($id, $data)
    {
        if ($id == 'system') {
            throw new Forbidden();
        }
        $newPassword = null;
        if (property_exists($data, 'password')) {
            $newPassword = $data->password;
            $data->password = $this->hashPassword($data->password);
        }

        if ($id == $this->getUser()->id) {
            unset($data->isActive);
        }

        $user = parent::updateEntity($id, $data);

        if (!is_null($newPassword)) {
            try {
                if ($user->isActive() && !empty($data->sendAccessInfo)) {
                    $this->sendPassword($user, $newPassword);
                }
            } catch (\Exception $e) {}
        }

        return $user;
    }

    protected function getInternalUserCount()
    {
        return $this->getEntityManager()->getRepository('User')->where(array(
            'isActive' => true,
            'isSuperAdmin' => false,
            'id!=' => 'system'
        ))->count();
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        if ($this->getConfig()->get('userLimit') && !$this->getUser()->get('isSuperAdmin')) {
            $userCount = $this->getInternalUserCount();
            if ($userCount >= $this->getConfig()->get('userLimit')) {
                throw new Forbidden('User limit '.$this->getConfig()->get('userLimit').' is reached.');
            }
        }
    }

    protected function beforeUpdateEntity(Entity $user, $data)
    {
        if ($this->getConfig()->get('userLimit') && !$this->getUser()->get('isSuperAdmin')) {
            if ($user->get('isActive') && $user->isAttributeChanged('isActive')) {
                $userCount = $this->getInternalUserCount();
                if ($userCount >= $this->getConfig()->get('userLimit')) {
                    throw new Forbidden('User limit '.$this->getConfig()->get('userLimit').' is reached.');
                }
            }
        }
    }

    protected function sendPassword(Entity $user, $password)
    {
        $emailAddress = $user->get('emailAddress');

        if (empty($emailAddress)) {
            return;
        }

        if (!$this->getConfig()->get('smtpServer')) {
            return;
        }

        $subject = $this->getLanguage()->translate('accountInfoEmailSubject', 'messages', 'User');
        $body = $this->getLanguage()->translate('accountInfoEmailBody', 'messages', 'User');

        $body = str_replace('{userName}', $user->get('userName'), $body);
        $body = str_replace('{password}', $password, $body);

        $siteUrl = $this->getConfig()->getSiteUrl() . '/';

        $body = str_replace('{siteUrl}', $siteUrl, $body);

        $this->getMailSender()->send(
            [
                'subject' => $subject,
                'body'    => $body,
                'isHtml'  => false,
                'to'      => $emailAddress
            ]
        );
    }

    protected function sendChangePasswordLink($requestId, $emailAddress, Entity $user = null)
    {
        if (empty($emailAddress)) {
            return;
        }

        if (!$this->getConfig()->get('smtpServer')) {
            throw new Error("SMTP credentials are not defined.");
        }

        $subject = $this->getLanguage()->translate('passwordChangeLinkEmailSubject', 'messages', 'User');
        $body = $this->getLanguage()->translate('passwordChangeLinkEmailBody', 'messages', 'User');

        $link = $this->getConfig()->get('siteUrl') . '?entryPoint=changePassword&id=' . $requestId;

        $body = str_replace('{link}', $link, $body);

        $this->getMailSender()->send(
            [
                'subject' => $subject,
                'body'    => $body,
                'isHtml'  => false,
                'to'      => $emailAddress
            ]
        );
    }

    public function deleteEntity($id)
    {
        if ($id == 'system') {
            throw new Forbidden();
        }
        if ($id == $this->getUser()->id) {
            throw new Forbidden();
        }
        return parent::deleteEntity($id);
    }

    public function afterUpdate(Entity $entity, array $data = array())
    {
        parent::afterUpdate($entity, $data);
        if (array_key_exists('rolesIds', $data) || array_key_exists('teamsIds', $data) || array_key_exists('isAdmin', $data)) {
            $this->clearRoleCache($entity->id);
        }
    }

    protected function clearRoleCache($id)
    {
        $this->getFileManager()->removeFile('data/cache/application/acl/' . $id . '.php');
    }

    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);
        $this->loadLastAccessField($entity);
    }

    public function loadLastAccessField(Entity $entity)
    {
        $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit');
        if (in_array('lastAccess', $forbiddenFieldList)) return;

        $authToken = $this->getEntityManager()->getRepository('AuthToken')->select(['id', 'lastAccess'])->where([
            'userId' => $entity->id
        ])->order('lastAccess', true)->findOne();

        $lastAccess = null;

        if ($authToken) {
            $lastAccess = $authToken->get('lastAccess');
        }

        $dt = null;

        if ($lastAccess) {
            try {
                $dt = new \DateTime($lastAccess);
            } catch (\Exception $e) {}
        }

        $where = [
            'userId' => $entity->id,
            'isDenied' => false
        ];

        if ($dt) {
            $where['requestTime>'] = $dt->format('U');
        }

        $authLogRecord = $this->getEntityManager()->getRepository('AuthLogRecord')
            ->select(['id', 'createdAt'])->where($where)->order('requestTime', true)->findOne();

        if ($authLogRecord) {
            $lastAccess = $authLogRecord->get('createdAt');
        }

        $entity->set('lastAccess', $lastAccess);
    }

    /**
     * @inheritDoc
     */
    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }
}
