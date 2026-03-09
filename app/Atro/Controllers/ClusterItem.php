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
use Atro\Core\Templates\Controllers\Base;

class ClusterItem extends Base
{
    public function actionReject($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $params = [];
        $recordService = $this->getRecordService();

        if (property_exists($data, 'where')) {
            $params['where'] = json_decode(json_encode($data->where), true);
        }

        if (property_exists($data, 'idList')) {
            $params['ids'] = $data->idList;
        }

        if (empty($params) && empty($data->id)) {
            throw new BadRequest($this->getLanguage()->translate('idOrIdListOrWhereRequired', 'exceptions', 'ClusterItem'));
        }

        if (empty($params) && !empty($data->id)) {
            $entity = $recordService->getEntity((string)$data->id);
            if (empty($entity)) {
                throw new NotFound();
            }
            $params['ids'][] = $data->id;
        }

        return $recordService->reject($params);
    }

    public function actionUnmerge($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $params = [];
        $recordService = $this->getRecordService();

        if (property_exists($data, 'where')) {
            $where = json_decode(json_encode($data->where), true);
            $params['where'] = $where;
        }

        if (property_exists($data, 'idList')) {
            $params['ids'] = $data->idList;
        }

        if (property_exists($data, 'id')) {
            $params['ids'] = [$data->id];
        }

        if (empty($params)) {
            throw new BadRequest($this->getLanguage()->translate('idOrIdListOrWhereRequired', 'exceptions', 'ClusterItem'));
        }

        return $recordService->unmerge($params);
    }

    public function actionUnreject($params, $data, $request)
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


        return $this->getRecordService()->unreject((string)$data->id, (string)$data->relationId);
    }

    public function actionConfirm($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'id') || empty($data->id)) {
            throw new BadRequest('ID is required.');
        }

        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $recordService = $this->getRecordService();

        $entity = $recordService->getEntity((string)$data->id);
        if (empty($entity)) {
            throw new NotFound();
        }

        return $recordService->confirm($entity);
    }
}
