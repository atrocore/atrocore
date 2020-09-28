<?php

namespace Espo\Core\Controllers;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Utils\Util;

class RecordTree extends Record
{
    public static $defaultAction = 'list';

    protected $defaultRecordServiceName = 'RecordTree';

    public function actionListTree($params, $data, $request)
    {
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $where = $request->get('where');
        $parentId = $request->get('parentId');
        $maxDepth = $request->get('maxDepth');
        $onlyNotEmpty = $request->get('onlyNotEmpty');

        $collection = $this->getRecordService()->getTree($parentId, array(
            'where' => $where,
            'onlyNotEmpty' => $onlyNotEmpty
        ), 0, $maxDepth);
        return array(
            'list' => $collection->toArray(),
            'path' => $this->getRecordService()->getTreeItemPath($parentId)
        );
    }

    public function getActionLastChildrenIdList($params, $data, $request)
    {
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $parentId = $request->get('parentId');

        return $this->getRecordService()->getLastChildrenIdList($parentId);
    }
}
