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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Base;
use Atro\Core\Exceptions\BadRequest;

class Storage extends Base
{
    public function actionCreateScanJob($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'id') || empty($data->id)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->createScanJob((string)$data->id, true);
    }

    public function actionUnlinkAllFiles($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'id') || empty($data->id)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check('File', 'delete')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->unlinkAllFiles((string)$data->id);
    }
}
