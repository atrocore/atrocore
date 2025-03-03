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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;

class UserProfile extends AbstractRecordController
{
    public function postActionResetDashboard($params, $data, $request)
    {
        if (empty($data->id)) {
            throw new BadRequest();
        }

        if (!$this->getUser()->isAdmin()) {
            if ($this->getUser()->id != $data->id) {
                throw new Forbidden();
            }
        }

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

    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionList($params, $data, $request)
    {
        throw new NotFound();
    }

    public function getActionListKanban($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionDelete($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionMassDelete($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionRestore($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionMassRestore($params, $data, $request)
    {
        throw new NotFound();
    }

//    public function actionCreateLink($params, $data, $request)
//    {
//        throw new NotFound();
//    }
//
//    public function actionRemoveLink($params, $data, $request)
//    {
//        throw new NotFound();
//    }
//
//    public function actionUnlinkAll($params, $data, $request)
//    {
//        throw new NotFound();
//    }

    public function actionFollow($params, $data, $request)
    {
        throw new NotFound();
    }

    public function postActionMassFollow($params, $data, $request)
    {
        throw new NotFound();
    }

    public function postActionMassUnfollow($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionTree($params, $data, $request): array
    {
        throw new NotFound();
    }

    public function actionSeed($params, $data, $request)
    {
        throw new NotFound();
    }
}
