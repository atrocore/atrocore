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
use Atro\Core\Templates\Controllers\Base;

class Cluster extends Base
{
    public function actionMerge($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($data->clusterId) || empty($data->sourceIds) || !is_array($data->sourceIds) || !($data->attributes instanceof \StdClass)) {
            throw new BadRequest();
        }

        $entity =  $this->getRecordService()->mergeItems($data->clusterId, $data->sourceIds, $data->attributes);

        return $entity->getValueMap();
    }
}
