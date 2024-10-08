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

namespace Atro\Core\Templates\Controllers;

use Atro\Controllers\AbstractRecordController;
use Atro\Core\Exceptions\BadRequest;

class ReferenceData extends AbstractRecordController
{
    public function actionListLinked($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionMassDelete($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionRestore($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionMassRestore($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionCreateLink($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionRemoveLink($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionUnlinkAll($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionFollow($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionUnfollow($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function postActionMassFollow($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function postActionMassUnfollow($params, $data, $request)
    {
        throw new BadRequest();
    }
}
