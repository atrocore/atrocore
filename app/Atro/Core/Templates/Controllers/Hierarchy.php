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

namespace Atro\Core\Templates\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;

class Hierarchy extends Base
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

    public function actionInheritAllFromParent($params, $data, $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->inheritAllFromParent((string)$data->id);
    }

    public function actionTree($params, $data, $request): array
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        if (!$this->getRecordService()->isHierarchy()) {
            return parent::actionTree($params, $data, $request);
        }

        if (empty($request->get('node')) && !empty($request->get('selectedId'))) {
            $sortParams = [
                'asc'    => $request->get('asc', 'true') === 'true',
                'sortBy' => $request->get('sortBy'),
            ];
            return $this->getRecordService()->getTreeDataForSelectedNode((string)$request->get('selectedId'), $sortParams);
        }

        $params = [
            'where'        => $this->prepareWhereQuery($request->get('where')),
            'foreignWhere' => $this->prepareWhereQuery($request->get('foreignWhere')),
            'link'         => (string)$request->get('link'),
            'scope'        => (string)$request->get('scope'),
            'asc'          => $request->get('asc', 'true') === 'true',
            'sortBy'       => $request->get('sortBy'),
            'isTreePanel'  => !empty($request->get('isTreePanel')),
            'offset'       => (int)$request->get('offset'),
            'maxSize'      => empty($request->get('maxSize')) ? $this->getConfig()->get('recordsPerPageSmall', 20) : (int)$request->get('maxSize')
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

        $params = [];

        if (!empty($request->get('ids'))) {
            $ids = (array)$request->get('ids');
            $params['ids'] = $ids;
        } else {
            $params = [
                'where'        => $this->prepareWhereQuery($request->get('where') ?? []),
                'foreignWhere' => $this->prepareWhereQuery($request->get('foreignWhere') ?? []),
                'link'         => (string)$request->get('link'),
                'scope'        => (string)$request->get('scope'),
                'offset'       => 0,
                'maxSize'      => 5000,
                'asc'          => true,
                'sortBy'       => 'id'
            ];
        }

        return $this->getRecordService()->getTreeData($params);
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
