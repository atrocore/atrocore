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

namespace Atro\Controllers;

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Utils\Language;

class User extends AbstractRecordController
{
    public function actionAcl($params, $data, $request)
    {
        $userId = $request->get('id');
        if (empty($userId)) {
            throw new Error();
        }

        $user = $this->getEntityManager()->getEntity('User', $userId);
        if (empty($user)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($user, 'read')) {
            throw new Forbidden();
        }

        return $this->getAclManager()->getMap($user);
    }

    public function postActionChangeExpiredPassword($params, $data, $request)
    {
        if (!property_exists($data, 'password')) {
            throw new BadRequest();
        }

        $user = $this->getUser();
        $expireDays = $this->getConfig()->get('passwordExpireDays', 0);
        if ($user->isSystem() || !$user->needToUpdatePassword($expireDays)) {
            throw new Forbidden();
        }

        return $this->getService('User')->changePassword($user->id, $data->password);
    }

    public function postActionChangeOwnPassword($params, $data, $request)
    {
        if ($this->getConfig()->get('resetPasswordViaEmailOnly', false)) {
            throw new BadRequest($this->getLanguage()->translate('changePasswordOnResetViaEmailOnly', 'messages', 'User'));
        }

        if (!property_exists($data, 'password') || !property_exists($data, 'currentPassword')) {
            throw new BadRequest();
        }

        return $this->getService('User')->changePassword($data->userId ?? $this->getUser()->id, $data->password, true, $data->currentPassword, $data->sendAccessInfo ?? false);
    }

    public function postActionResetPassword($params, $data, $request)
    {
        if (!property_exists($data, 'userId')) {
            throw new BadRequest();
        }

        return $this->getService('User')->resetPassword($data->userId);
    }

    public function postActionChangePasswordByRequest($params, $data, $request)
    {
        if (empty($data->requestId) || empty($data->password)) {
            throw new BadRequest();
        }

        $p = $this->getEntityManager()->getRepository('PasswordChangeRequest')
            ->where(['requestId' => $data->requestId])
            ->findOne();

        if (!$p) {
            throw new Forbidden();
        }
        $userId = $p->get('userId');
        if (!$userId) {
            throw new Error();
        }

        try {
            $changed = $this->getService('User')->changePassword($userId, $data->password);
        } catch (BadRequest $e) {
            // do not delete request on password validation error
            throw $e;
        } catch (\Throwable $e) {
            $this->getEntityManager()->removeEntity($p);
            throw $e;
        }

        if (!empty($changed)) {
            $this->getEntityManager()->removeEntity($p);
            return array(
                'url' => $p->get('url')
            );
        }
    }

    public function postActionPasswordChangeRequest($params, $data, $request)
    {
        if (empty($data->userName) || empty($data->emailAddress)) {
            throw new BadRequest();
        }

        $userName = $data->userName;
        $emailAddress = $data->emailAddress;
        $url = null;
        if (!empty($data->url)) {
            $url = $data->url;
        }

        return $this->getService('User')->passwordChangeRequest($userName, $emailAddress, $url);
    }

    public function actionCreateLink($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::actionCreateLink($params, $data, $request);
    }

    public function actionRemoveLink($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::actionRemoveLink($params, $data, $request);
    }

    protected function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }
}
