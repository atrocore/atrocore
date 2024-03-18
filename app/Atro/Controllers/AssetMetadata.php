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
use Atro\Core\Exceptions\Forbidden;

class AssetMetadata extends Base
{
    public function actionCreate($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionUpdate($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionDelete($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMassDelete($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionCreateLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionRemoveLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function actionMerge($params, $data, $request)
    {
        throw new Forbidden();
    }
}
