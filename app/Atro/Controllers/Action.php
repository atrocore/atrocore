<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Controllers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Templates\Controllers\Base;

class Action extends Base
{
    public function actionExecuteNow($params, $data, $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'actionId')) {
            throw new BadRequest();
        }

        return $this
            ->getRecordService()
            ->executeNow((string)$data->actionId, $data);
    }
}
