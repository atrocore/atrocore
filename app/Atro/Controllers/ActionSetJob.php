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

use Atro\Core\Templates\Controllers\Base;
use Espo\Core\Exceptions\NotFound;

class ActionSetJob extends Base
{
    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionCreateLink($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionRemoveLink($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionUnlinkAll($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionFollow($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionUnfollow($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionMerge($params, $data, $request)
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
}
