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

use Espo\Core\Controllers\Base;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;

class MassActions extends Base
{
    public function actionUpsert($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        $useQueue = $request->headers('use-queue');
        $viaQm = $useQueue === '1' || strtolower((string)$useQueue) === 'true';

        $data = (array)$data;

        if ($viaQm) {
            return $this->getService('MassActions')->upsertViaQm($data);
        }

        return $this->getService('MassActions')->upsert($data);
    }

    public function actionAddRelation($params, $data, $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'where') || !property_exists($data ,'foreignWhere') || !isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        $where = json_decode(json_encode($data->where), true);
        $foreignWhere = json_decode(json_encode($data->foreignWhere), true);
        return $this->getService('MassActions')
            ->addRelationByWhere($where, $foreignWhere, $params['scope'], $params['link']);
    }

    public function actionRemoveRelation($params, $data, $request): array
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'where') || !property_exists($data ,'foreignWhere') || !isset($params['scope']) || !isset($params['link'])) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($params['scope'], 'edit')) {
            throw new Forbidden();
        }

        $where = json_decode(json_encode($data->where), true);
        $foreignWhere = json_decode(json_encode($data->foreignWhere), true);
        return $this->getService('MassActions')
            ->removeRelationByWhere($where, $foreignWhere, $params['scope'], $params['link']);
    }
}
