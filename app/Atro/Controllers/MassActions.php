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
use Slim\Http\Request;

class MassActions extends AbstractController
{
    public function actionUpsert($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        $useQueue = $request->headers('use-queue');
        $viaJob = $useQueue === '1' || strtolower((string)$useQueue) === 'true';

        $data = (array)$data;

        if ($viaJob) {
            return $this->getService('MassActions')->upsertViaJob($data);
        }

        return $this->getService('MassActions')->upsert($data);
    }

    public function actionAddRelation($params, $data, $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        $relationData = json_decode(json_encode($data?->data ?? ''), true);

        if (property_exists($data, 'where') && property_exists($data, 'foreignWhere')) {
            $where = json_decode(json_encode($data->where), true);
            $foreignWhere = json_decode(json_encode($data->foreignWhere), true);

            return $this->getService('MassActions')->addRelationByWhere($where, $foreignWhere, $params['scope'], $params['link'], $relationData);
        } else if (property_exists($data, 'ids') && property_exists($data, 'foreignIds')) {
            $ids = $data->ids;
            $foreignIds = $data->foreignIds;

            if (!is_array($ids) || !is_array($foreignIds)) {
                throw new BadRequest();
            }

            return $this->getService('MassActions')->addRelation($ids, $foreignIds, $params['scope'], $params['link'], $relationData);
        }

        throw new BadRequest();
    }

    public function actionRemoveRelation($params, $data, $request): array
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        if (!isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        $relationData = json_decode(json_encode($data?->data), true);

        if (property_exists($data, 'where') && property_exists($data, 'foreignWhere')) {
            $where = json_decode(json_encode($data->where), true);
            $foreignWhere = json_decode(json_encode($data->foreignWhere), true);

            return $this->getService('MassActions')->removeRelationByWhere($where, $foreignWhere, $params['scope'], $params['link'], $relationData);
        } else if (property_exists($data, 'ids') && property_exists($data, 'foreignIds')) {
            $ids = $data->ids;
            $foreignIds = $data->foreignIds;

            if (!is_array($ids) || !is_array($foreignIds)) {
                throw new BadRequest();
            }

            return $this->getService('MassActions')->removeRelation($ids, $foreignIds, $params['scope'], $params['link'], $relationData);
        }

        throw new BadRequest();
    }
}
