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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Controllers\Base;
use Atro\Core\Exceptions\NotFound;

class Job extends Base
{
    /**
     * @inheritdoc
     */
    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionMassActionStatus($params, $data, $request)
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        $jobId = $request->get('id');
        if (empty($jobId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getMassActionJobStatus((string)$jobId);
    }
}
