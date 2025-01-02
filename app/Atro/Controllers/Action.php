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
use Espo\Core\Templates\Controllers\Base;

class Action extends Base
{
    public function actionExecuteNow($params, $data, $request): array
    {
        if (!$request->isPost() || !property_exists($data, 'actionId')) {
            throw new BadRequest();
        }

        return $this
            ->getRecordService()
            ->executeNow((string)$data->actionId, $data);
    }

    public function actionExecuteRecordAction($params, $data, $request): array
    {
        if (!$request->isPost() || !property_exists($data, 'actionId') || !property_exists($data, 'entityId') || !property_exists($data, 'actionType')) {
            throw new BadRequest();
        }

        return $this
            ->getRecordService()
            ->executeRecordAction((string)$data->actionId, (string)$data->entityId, (string)$data->actionType);
    }

    public function actionDynamicActions($params, $data, $request)
    {
        if (!$request->isGet() || empty($params['id']) || empty($params['scope'])) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getRecordDynamicActions((string)$params['scope'], (string)$params['id'], (string)$request->get('display'));
    }
}
