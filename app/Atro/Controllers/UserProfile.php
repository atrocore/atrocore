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

namespace Atro\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;

class UserProfile extends AbstractController
{
    public function actionRead($params, $data, $request)
    {
        $id = $params['id'] ?? $this->getUser()->get('id');

        if ($id !== $this->getUser()->get('id') && !$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $GLOBALS['readingUserProfile'] = true;
        $entity = $this->getServiceFactory()->create('User')->readEntity($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        $res = [];
        foreach ($this->getUserProfileFields() as $field) {
            $res[$field] = $entity->get($field);
        }

        if (!empty($entity->get('localeId'))) {
            $locale = $this->getEntityManager()->getRepository('Locale')->get($entity->get('localeId'));
            if (!empty($locale)) {
                $res['localeId'] = $locale->get('id');
                $res['localeName'] = $locale->get('name');
            }
        }

        if (!empty($entity->get('styleId'))) {
            $style = $this->getEntityManager()->getRepository('Style')->get($entity->get('styleId'));
            if (!empty($style)) {
                $res['styleId'] = $style->get('id');
                $res['styleName'] = $style->get('name');
            }
        }

        return $this->prepareUserProfileData($res);
    }

    public function actionPatch($params, $data, $request)
    {
        return $this->actionUpdate($params, $data, $request);
    }

    public function actionUpdate($params, $data, $request)
    {
        echo '<pre>';
        print_r('actionUpdate');
        die();

        if (!$request->isPut() && !$request->isPatch()) {
            throw new BadRequest();
        }

        $id = $params['id'] ?? $this->getUser()?->get('id');

        if (empty($id)) {
            throw new BadRequest();
        }

        $this->handleUserAccess($id);

        $fields = $this->getUserProfileFields();

        foreach ($data as $field => $val) {
            if (!in_array($field, $fields) || in_array($field, ['userName', 'emailAddress'])) {
                unset($data->$field);
            }
        }

        $data->_skipIsEntityUpdated = true;

        $GLOBALS['updatingUserProfile'] = true;
        if ($entity = $this->getServiceFactory()->create('User')->updateEntity($id, $data)) {
            return $this->prepareUserProfileData($entity->getValueMap());
        }

        throw new Error();
    }

    public function postActionResetDashboard($params, $data, $request)
    {
        if (empty($data->id)) {
            throw new BadRequest();
        }

        $this->handleUserAccess($data->id);

        $user = $this->getEntityManager()->getEntity('User', $data->id);
        if (empty($user)) {
            throw new NotFound();
        }

        $user->set([
            'dashboardLayout' => null,
            'dashletsOptions' => null
        ]);

        $this->getEntityManager()->saveEntity($user);

        if (empty($defaultLayout = $this->getUser()->get('layoutProfile'))) {
            $defaultLayout = $this->getEntityManager()
                ->getRepository('LayoutProfile')
                ->where(['isDefault' => true])
                ->findOne();
        }

        return (object)[
            'dashboardLayout' => !empty($defaultLayout) ? $defaultLayout->get('dashboardLayout') : null,
            'dashletsOptions' => !empty($defaultLayout) ? $defaultLayout->get('dashletsOptions') : null,
        ];
    }

    public function getUserProfileFields(): array
    {
        $fields = ['id'];
        foreach ($this->getMetadata()->get('entityDefs.UserProfile.fields', []) as $field => $fieldDefs) {
            if ($fieldDefs['type'] === 'link' || $fieldDefs['type'] === 'file') {
                $fields[] = "{$field}Id";
                $fields[] = "{$field}Name";
            } else {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    protected function handleUserAccess(string $userId): void
    {
        if (!$this->getUser()->isAdmin()) {
            if ($this->getUser()->id != $userId) {
                throw new Forbidden();
            }
        }
    }

    protected function prepareUserProfileData($data)
    {
        if (is_array($data)) {
            $data = json_decode(json_encode($data));
        }
        $this->getService('App')->prepareLayoutProfileData($data);

        return $data;
    }
}
