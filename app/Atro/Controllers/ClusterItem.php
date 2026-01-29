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
use Atro\Core\Templates\Controllers\Base;

class ClusterItem extends Base
{
    public function actionReject($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'id')) {
            throw new BadRequest('ID is required.');
        }

        if (!property_exists($data, 'relationId')) {
            throw new BadRequest('Rejected cluster item id is required.');
        }

        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }


        return $this->getRecordService()->reject((string)$data->id, (string)$data->relationId);
    }

    public function actionUnreject($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'id')) {
            throw new BadRequest('ID is required.');
        }

        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }


        return $this->getRecordService()->unreject((string)$data->id);
    }

    public function actionConfirm($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'id')) {
            throw new BadRequest('ID is required.');
        }

        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->confirm((string)$data->id);
    }
}
