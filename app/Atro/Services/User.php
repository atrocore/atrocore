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

namespace Atro\Services;

use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Utils\Util;
use Atro\Core\Utils\Language;
use Espo\ORM\Entity;

class User extends Record
{
    protected $internalAttributeList = ['password'];

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

    public function getEntity($id = null)
    {
        if (isset($id) && $id == 'system') {
            throw new Forbidden();
        }

        return parent::getEntity($id);
    }

    public function findEntities($params)
    {
        if (empty($params['where'])) {
            $params['where'] = [];
        }
        $params['where'][] = [
            'type'  => 'notEquals',
            'field' => 'id',
            'value' => 'system'
        ];

        return parent::findEntities($params);
    }

    private function isValidPassword(string $password): bool
    {
        if ($this->getConfig()->has('passwordRegexPattern')) {
            $passwordRegex = $this->getConfig()->get('passwordRegexPattern');
        } else {
            $passwordRegex = $this->getMetadata()->get([
                'entityDefs',
                'Settings',
                'fields',
                'passwordRegexPattern',
                'default'
            ]);
        }

        if (!empty($passwordRegex) && !preg_match($passwordRegex, $password)) {
            return false;
        }

        return true;
    }

    public function changePassword(
        $userId,
        $password,
        $checkCurrentPassword = false,
        $currentPassword = null,
        $sendAccessInfo = false
    ) {
        $user = $this->getEntityManager()->getEntity('User', $userId);
        if (!$user) {
            throw new NotFound();
        }

        if ($this->getUser()->id != $userId && !$this->getAcl()->check($user, 'edit')) {
            throw new Forbidden();
        }

        if (empty($password)) {
            throw new Error('Password can\'t be empty.');
        }

        if (!$this->isValidPassword($password)) {
            throw new BadRequest($this->getLanguage()->translate('newPasswordInvalid', 'messages', 'User'));
        }

        if ($checkCurrentPassword) {
            $passwordHash = new \Espo\Core\Utils\PasswordHash($this->getConfig());
            $u = $this->getEntityManager()->getRepository('User')->where(array(
                'id'       => $this->getUser()->id,
                'password' => $passwordHash->hash($currentPassword)
            ))->findOne();
            if (!$u) {
                throw new Forbidden();
            }
        }

        $user->set('password', $this->hashPassword($password));
        $user->set('passwordUpdatedAt', (new \DateTime())->format('Y-m-d H:i:s'));
        $user->set('passwordUpdatedById', $this->getUser()->get('id'));

        $this->getEntityManager()->saveEntity($user);

        $this->invalidateUserTokens($userId);

        if ($sendAccessInfo) {
            $this->sendPassword($user, $password);
        }

        return true;
    }

    public function passwordChangeRequest($userName, $emailAddress, $url = null, $isResetAction = false)
    {
        $user = $this->getEntityManager()->getRepository('User')
            ->where([
                'userName'     => $userName,
                'emailAddress' => $emailAddress
            ])
            ->findOne();

        if (empty($user)) {
            throw new NotFound();
        }

        if (!$user->isActive()) {
            throw new NotFound();
        }

        $userId = $user->id;

        $passwordChangeRequest = $this->getEntityManager()->getRepository('PasswordChangeRequest')
            ->where(['userId' => $userId])
            ->findOne();

        if ($passwordChangeRequest) {
            throw new Forbidden();
        }

        $requestId = Util::generateId();

        $passwordChangeRequest = $this->getEntityManager()->getEntity('PasswordChangeRequest');
        $passwordChangeRequest->set([
            'userId'    => $userId,
            'requestId' => $requestId,
            'url'       => $url
        ]);

        $this->sendChangePasswordLink($requestId, $emailAddress, null, $isResetAction);

        $this->getEntityManager()->saveEntity($passwordChangeRequest);

        if (!$passwordChangeRequest->id) {
            throw new Error();
        }

        return true;
    }

    protected function hashPassword($password)
    {
        $config = $this->getConfig();
        $passwordHash = new \Espo\Core\Utils\PasswordHash($config);

        return $passwordHash->hash($password);
    }

    public function createEntity($attachment)
    {
        $newPassword = null;
        if (property_exists($attachment, 'password')) {
            $newPassword = $attachment->password;
            $attachment->password = $this->hashPassword($attachment->password);
        }

        $user = parent::createEntity($attachment);

        if (!is_null($newPassword) && !empty($attachment->sendAccessInfo) && $user->isActive()) {
            try {
                $this->sendPassword($user, $newPassword);
            } catch (\Exception $e) {
            }
        }

        return $user;
    }

    public function updateEntity($id, $data)
    {
        if ($id == 'system') {
            throw new Forbidden();
        }

        if (property_exists($data, 'password')) {
            unset($data->password);
        }

        if ($id == $this->getUser()->id) {
            unset($data->isActive);
        }

        return parent::updateEntity($id, $data);
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $entity->set('passwordUpdatedAt', (new \DateTime())->format('Y-m-d H:i:s'));
        $entity->set('passwordUpdatedById', $this->getUser()->get('id'));
    }

    protected function sendPassword(Entity $user, $password)
    {
        $emailAddress = $user->get('emailAddress');

        if (empty($emailAddress)) {
            return;
        }

        if (!$this->getConfig()->get('notificationSmtpConnectionId')) {
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

    public function resetPassword(string $userId): bool
    {
        /** @var \Atro\Entities\User $user */
        $user = $this->getRepository()->get($userId);
        if (!$user) {
            throw new NotFound();
        }

        if ($userId != $this->getUser()->id && !$this->getAcl()->check($user, 'edit')) {
            throw new Forbidden();
        }

        if (!$user->get('emailAddress')) {
            throw new BadRequest('User does not have an email');
        }

        $this->passwordChangeRequest($user->get('userName'), $user->get('emailAddress'), null, true);

        $this->invalidateUserTokens($userId);

        $user->set('password', null);
        $this->getEntityManager()->saveEntity($user);

        return true;
    }

    private function invalidateUserTokens(string $userId): void
    {
        $authTokens = $this->getEntityManager()->getRepository('AuthToken')
            ->select(['id', 'lastAccess'])
            ->where(['userId' => $userId])
            ->find();

        foreach ($authTokens as $authToken) {
            $authToken->set('isActive', false);
            $this->getEntityManager()->saveEntity($authToken);
        }
    }

    protected function sendChangePasswordLink(
        $requestId,
        $emailAddress,
        Entity $user = null,
        bool $isResetAction = false
    ) {
        if (empty($emailAddress)) {
            return;
        }

        if (!$this->getConfig()->get('notificationSmtpConnectionId')) {
            throw new Error("SMTP credentials are not defined.");
        }

        $link = $this->getConfig()->get('siteUrl') . '?entryPoint=changePassword&id=' . $requestId;
        $emailTemplate = $this->getEntityManager()->getRepository('EmailTemplate')->getEntityByCode($isResetAction ? 'emailPasswordReset' : 'emailPasswordChangeRequest');

        if (empty($emailTemplate)) {
            throw new Error("EmailTemplate does not exist.");
        }

        $twig = $this->getContainer()->get('twig');
        $data = ['link' => $link, 'user' => $user];

        $this->getMailSender()->send([
            'subject' => $twig->renderTemplate($emailTemplate->get('subject'), $data),
            'body'    => $twig->renderTemplate($emailTemplate->get('body'), $data),
            'to'      => $emailAddress,
            'isHtml'  => true
        ]);
    }

    public function deleteEntity($id)
    {
        if ($id == 'system' || $id == $this->getUser()->id) {
            throw new Forbidden();
        }

        return parent::deleteEntity($id);
    }

    public function afterUpdate(Entity $entity, array $data = array())
    {
        parent::afterUpdate($entity, $data);

        if (
            array_key_exists('rolesIds', $data)
            || array_key_exists('teamsIds', $data)
            || array_key_exists('isAdmin', $data)
        ) {
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
        if (in_array('lastAccess', $forbiddenFieldList)) {
            return;
        }

        $authToken = $this->getEntityManager()->getRepository('AuthToken')
            ->select(['id', 'lastAccess'])
            ->where([
                'userId' => $entity->id
            ])
            ->order('lastAccess', true)
            ->findOne();

        $lastAccess = null;

        if ($authToken) {
            $lastAccess = $authToken->get('lastAccess');
        }

        $dt = null;

        if ($lastAccess) {
            try {
                $dt = new \DateTime($lastAccess);
            } catch (\Exception $e) {
            }
        }

        $where = [
            'userId'   => $entity->id,
            'isDenied' => false
        ];

        if ($dt) {
            $where['requestTime>'] = $dt->format('U');
        }

        $authLogRecord = $this->getEntityManager()->getRepository('AuthLogRecord')
            ->select(['id', 'createdAt'])
            ->where($where)
            ->order('requestTime', true)
            ->findOne();

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

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getMailSender()
    {
        return $this->getContainer()->get('mailSender');
    }

    protected function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }

    protected function getFileManager()
    {
        return $this->getContainer()->get('fileManager');
    }

    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }
}
