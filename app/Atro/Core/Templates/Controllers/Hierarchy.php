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

namespace Atro\Core\Templates\Controllers;

use Espo\Core\Controllers\Record;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;

class Hierarchy extends Record
{
    public function actionInheritAllForChildren($params, $data, $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->inheritAllForChildren((string)$data->id);
    }

    public function actionTree($params, $data, $request): array
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        if (empty($request->get('node')) && !empty($request->get('selectedId'))) {
            return $this->getRecordService()->getTreeDataForSelectedNode((string)$request->get('selectedId'));
        }

        $params = [
            'where'       => $this->prepareWhereQuery($request->get('where')),
            'asc'         => $request->get('asc', 'true') === 'true',
            'sortBy'      => $request->get('sortBy'),
            'isTreePanel' => !empty($request->get('isTreePanel')),
            'offset'      => (int)$request->get('offset'),
            'maxSize'     => empty($request->get('maxSize')) ? $this->getConfig()->get('recordsPerPageSmall', 20) : (int)$request->get('maxSize')
        ];

        return $this->getRecordService()->getChildren((string)$request->get('node'), $params);
    }

    public function actionTreeData($params, $data, $request): array
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        if (!empty($request->get('ids'))) {
            $ids = (array)$request->get('ids');
        } elseif (!empty($request->get('where'))) {
            $params = [
                'select'  => ['id'],
                'where'   => $this->prepareWhereQuery($request->get('where')),
                'offset'  => 0,
                'maxSize' => 5000,
                'asc'     => true,
                'sortBy'  => 'id'
            ];

            $result = $this->getRecordService()->findEntities($params);
            if (!empty($result['total'])) {
                $ids = array_column($result['collection']->toArray(), 'id');
            }
        }

        if (empty($ids)) {
            return [
                'total' => 0,
                'tree'  => []
            ];
        }

        return $this->getRecordService()->getTreeData($ids);
    }

    public function actionInheritField($params, $data, $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'field') || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->inheritField((string)$data->field, (string)$data->id);
    }

    public function actionInheritAll($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'id') || !property_exists($data, 'link')) {
            throw new BadRequest();
        }

        return $this->getRecordService()->inheritAllForLink((string)$data->id, (string)$data->link);
    }
}
