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
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\PseudoTransactionManager;

abstract class AbstractRecordController extends AbstractController
{
    const MAX_SIZE_LIMIT = 200;

    public static $defaultAction = 'list';

    protected $defaultRecordServiceName = 'Record';

    public function actionRead($params, $data, $request)
    {
        $id = $params['id'];
        $entity = $this->getRecordService()->readEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        return $entity->getValueMap();
    }

    public function actionPatch($params, $data, $request)
    {
        return $this->actionUpdate($params, $data, $request);
    }

    public function actionCreate($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        $service = $this->getRecordService();

        if ($entity = $service->createEntity($data)) {
            return $entity->getValueMap();
        }

        throw new Error();
    }

    public function actionUpdate($params, $data, $request)
    {
        if (!$request->isPut() && !$request->isPatch()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        $id = $params['id'];

        if ($entity = $this->getRecordService()->updateEntity($id, $data)) {
            return $entity->getValueMap();
        }

        throw new Error();
    }

    public function actionList($params, $data, $request)
    {
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $where = $this->prepareWhereQuery($request->get('where'));
        $offset = $request->get('offset');
        $maxSize = $request->get('maxSize');
        $asc = $request->get('asc', 'true') === 'true';
        $sortBy = $request->get('sortBy');
        $q = $request->get('q');
        $textFilter = $request->get('textFilter');

        if (empty($maxSize)) {
            $maxSize = self::MAX_SIZE_LIMIT;
        }

        $params = array(
            'where'      => $where,
            'offset'     => $offset,
            'maxSize'    => $maxSize,
            'asc'        => $asc,
            'sortBy'     => $sortBy,
            'q'          => $q,
            'textFilter' => $textFilter,
            'disableCount' => $request->get('disableCount', false) === 'true'
        );

        $this->fetchListParamsFromRequest($params, $request, $data);

        $result = $this->getRecordService()->findEntities($params);

        return array(
            'total' => $result['total'],
            'list'  => isset($result['collection']) ? $result['collection']->getValueMapList() : $result['list']
        );
    }

    public function getActionListKanban($params, $data, $request)
    {
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $where = $this->prepareWhereQuery($request->get('where'));
        $offset = $request->get('offset');
        $maxSize = $request->get('maxSize');
        $asc = $request->get('asc', 'true') === 'true';
        $sortBy = $request->get('sortBy');
        $q = $request->get('q');
        $textFilter = $request->get('textFilter');

        if (empty($maxSize)) {
            $maxSize = self::MAX_SIZE_LIMIT;
        }

        $params = array(
            'where'      => $where,
            'offset'     => $offset,
            'maxSize'    => $maxSize,
            'asc'        => $asc,
            'sortBy'     => $sortBy,
            'q'          => $q,
            'textFilter' => $textFilter
        );

        $this->fetchListParamsFromRequest($params, $request, $data);

        $result = $this->getRecordService()->getListKanban($params);

        return (object)[
            'total'          => $result->total,
            'list'           => $result->collection->getValueMapList(),
            'additionalData' => $result->additionalData
        ];
    }

    protected function fetchListParamsFromRequest(&$params, $request, $data)
    {
        if ($request->get('primaryFilter')) {
            $params['primaryFilter'] = $request->get('primaryFilter');
        }
        if ($request->get('boolFilterList')) {
            $params['boolFilterList'] = $request->get('boolFilterList');
        }
        if ($request->get('filterList')) {
            $params['filterList'] = $request->get('filterList');
        }

        if ($request->get('select')) {
            $params['select'] = explode(',', $request->get('select'));
        }
    }

    public function actionListLinked($params, $data, $request)
    {
        $id = $params['id'];
        $link = $params['link'];

        $where = $this->prepareWhereQuery($request->get('where'));
        $offset = $request->get('offset');
        $maxSize = $request->get('maxSize');
        $asc = $request->get('asc', 'true') === 'true';
        $sortBy = $request->get('sortBy');
        $q = $request->get('q');
        $textFilter = $request->get('textFilter');

        if (empty($maxSize)) {
            $maxSize = self::MAX_SIZE_LIMIT;
        }

        $params = array(
            'where'      => $where,
            'offset'     => $offset,
            'maxSize'    => $maxSize,
            'asc'        => $asc,
            'sortBy'     => $sortBy,
            'q'          => $q,
            'textFilter' => $textFilter
        );

        $this->fetchListParamsFromRequest($params, $request, $data);

        $result = $this->getRecordService()->findLinkedEntities($id, $link, $params);

        if (isset($result['collection'])) {
            $list = $result['collection']->getValueMapList();
        } elseif (isset($result['list'])) {
            $list = $result['list'];
        } else {
            $list = [];
        }

        return array(
            'total' => $result['total'],
            'list'  => $list
        );
    }

    public function actionDelete($params, $data, $request)
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        $id = $params['id'];

        $method = 'deleteEntity';

        $permanently = trim((string)$this->getRecordService()::getHeader('permanently'));
        if ($permanently && (strtolower($permanently) === 'true') || $permanently === '1') {
            $method = 'deleteEntityPermanently';
        }

        if ($this->getRecordService()->$method($id)) {
            return true;
        }

        throw new Error();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        if (!$request->isPut()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }
        if (empty($data->attributes)) {
            throw new BadRequest();
        }

        $params = array();
        if (property_exists($data, 'where') && !empty($data->byWhere)) {
            $params['where'] = json_decode(json_encode($data->where), true);
            if (property_exists($data, 'selectData')) {
                $params['selectData'] = json_decode(json_encode($data->selectData), true);
            }
        } else {
            if (property_exists($data, 'ids')) {
                $params['ids'] = $data->ids;
            }
        }

        $attributes = $data->attributes;

        $idsUpdated = $this->getRecordService()->massUpdate($attributes, $params);

        return $idsUpdated;
    }

    public function actionMassDelete($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check($this->name, 'delete')) {
            throw new Forbidden();
        }

        $params = array();
        if (property_exists($data, 'where') && !empty($data->byWhere)) {
            $where = json_decode(json_encode($data->where), true);
            $params['where'] = $where;
            if (property_exists($data, 'selectData')) {
                $params['selectData'] = json_decode(json_encode($data->selectData), true);
            }
        }
        if (property_exists($data, 'ids')) {
            $params['ids'] = $data->ids;
        }
        if (property_exists($data, 'permanently')) {
            $params['permanently'] = $data->permanently;
        }

        return $this->getRecordService()->massRemove($params);
    }

    public function actionRestore($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        return !empty($this->getRecordService()->restoreEntity((string)$data->id));
    }

    public function actionMassRestore($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        $params = array();
        if (property_exists($data, 'where') && !empty($data->byWhere)) {
            $where = json_decode(json_encode($data->where), true);
            $params['where'] = $where;
            if (property_exists($data, 'selectData')) {
                $params['selectData'] = json_decode(json_encode($data->selectData), true);
            }
        }
        if (property_exists($data, 'ids')) {
            $params['ids'] = $data->ids;
        }

        return $this->getRecordService()->massRestore($params);
    }

    public function actionCreateLink($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($params['id']) || empty($params['link'])) {
            throw new BadRequest();
        }

        $id = $params['id'];
        $link = $params['link'];
        $shouldDuplicateForeign = !empty($data->shouldDuplicateForeign);

        if (!empty($data->massRelate)) {
            if (!is_array($data->where)) {
                throw new BadRequest();
            }
            $where = json_decode(json_encode($data->where), true);

            $selectData = null;
            if (isset($data->selectData) && is_array($data->selectData)) {
                $selectData = json_decode(json_encode($data->selectData), true);
            }
             if($shouldDuplicateForeign){
                 $this->getRecordService()->duplicateAndLinkEntityMass($id, $link, $where, $selectData);
             }else{
                 $this->getRecordService()->linkEntityMass($id, $link, $where, $selectData);
             }
             $this->getRecordService()->handleLinkEntitiesErrors($id, $link, $shouldDuplicateForeign);
             return true;
        } else {
            $foreignIdList = array();
            if (isset($data->id)) {
                $foreignIdList[] = $data->id;
            }
            if (isset($data->ids) && is_array($data->ids)) {
                foreach ($data->ids as $foreignId) {
                    $foreignIdList[] = $foreignId;
                }
            }

            $result = false;
            foreach ($foreignIdList as $foreignId) {
                if($shouldDuplicateForeign){
                    $result = $this->getRecordService()->duplicateAndLinkEntity($id, $link, $foreignId);
                }else{
                    $result = $this->getRecordService()->linkEntity($id, $link, $foreignId);
                }
            }
            if ($result) {
                $this->getRecordService()->handleLinkEntitiesErrors($id, $link, $shouldDuplicateForeign);
                return  true;
            }
        }

        throw new Error();
    }

    public function actionRemoveLink($params, $data, $request)
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        $id = $params['id'];
        $link = $params['link'];

        if (empty($params['id']) || empty($params['link'])) {
            throw new BadRequest();
        }

        $foreignIdList = [];
        if (isset($data->id)) {
            $foreignIdList[] = $data->id;
        }
        if (isset($data->ids) && is_array($data->ids)) {
            foreach ($data->ids as $foreignId) {
                $foreignIdList[] = $foreignId;
            }
        }

        if (!empty($request->get('ids'))) {
            $foreignIdList = explode(',', $request->get('ids'));
        }

        $service = $this->getRecordService();

        $result = false;
        foreach ($foreignIdList as $foreignId) {
            if ($service->unlinkEntity($id, $link, $foreignId)) {
                $result = $result || true;
            }
        }

        if ($result) {
            return true;
        }

        throw new Error();
    }

    public function actionUnlinkAll($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'id') || !property_exists($data, 'link')) {
            throw new BadRequest();
        }

        return $this->getRecordService()->unlinkAll((string)$data->id, (string)$data->link);
    }

    public function actionFollow($params, $data, $request)
    {
        if (!$request->isPut()) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check($this->name, 'stream')) {
            throw new Forbidden();
        }
        $id = $params['id'];
        return $this->getRecordService()->follow($id);
    }

    public function actionUnfollow($params, $data, $request)
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }
        $id = $params['id'];
        return $this->getRecordService()->unfollow($id);
    }

    public function actionMerge($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($data->targetId) || empty($data->sourceIds) || !is_array($data->sourceIds) || !($data->attributes instanceof \StdClass)) {
            throw new BadRequest();
        }
        $targetId = $data->targetId;
        $sourceIds = $data->sourceIds;
        $attributes = $data->attributes;

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->merge($targetId, $sourceIds, $attributes);
    }

    public function postActionGetDuplicateAttributes($params, $data, $request)
    {
        if (empty($data->id)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->getDuplicateAttributes($data->id);
    }

    public function postActionMassFollow($params, $data, $request)
    {
        if (!$this->getAcl()->check($this->name, 'stream')) {
            throw new Forbidden();
        }

        if (property_exists($data, 'where') && !empty($data->byWhere)) {
            $params['where'] = json_decode(json_encode($data->where), true);
            if (property_exists($data, 'selectData')) {
                $params['selectData'] = json_decode(json_encode($data->selectData), true);
            }
        } else {
            if (property_exists($data, 'ids')) {
                $params['ids'] = $data->ids;
            }
        }

        return $this->getRecordService()->massFollow($params);
    }

    public function postActionMassUnfollow($params, $data, $request)
    {
        if (!$this->getAcl()->check($this->name, 'stream')) {
            throw new Forbidden();
        }

        if (property_exists($data, 'where') && !empty($data->byWhere)) {
            $params['where'] = json_decode(json_encode($data->where), true);
            if (property_exists($data, 'selectData')) {
                $params['selectData'] = json_decode(json_encode($data->selectData), true);
            }
        } else {
            if (property_exists($data, 'ids')) {
                $params['ids'] = $data->ids;
            }
        }

        return $this->getRecordService()->massUnfollow($params);
    }

    public function actionSeed($params, $data, $request)
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $seed = $this->getEntityManager()->getRepository($this->name)->get();
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $seed->getEntityType(), 'fields'], []) as $name => $defs) {
            // send only values renderer via twig
            if (!empty($defs['type']) && $defs['type'] === 'varchar' && !empty($defs['default'])) {
                $default = $defs['default'];
                if (strpos($default, '{{') >= 0 && strpos($default, '}}') >= 0 && $seed->has($name)) {
                    $result[$name] = $seed->get($name);
                }
            }
        }

        return $result;
    }

    public function actionGetMassActionItemsCount($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'jobIds') || !property_exists($data, 'action')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }
        $params = [
            "jobIds"        => $data->jobIds,
            "action"        => $data->action,
            "previousCount" => $data->previousCount ?? 0
        ];

        return $this->getRecordService()->getMassActionItemsCount($params);

    }

    protected function prepareWhereQuery($where)
    {
        if (is_string($where)) {
            $where = json_decode(str_replace(['"{', '}"', '\"', '\n'], ['{', '}', '"', ''], $where), true);
        }

        return $where;
    }

    protected function getRecordService(?string $name = null)
    {
        if (empty($name)) {
            $name = $this->name;
        }

        if ($this->getServiceFactory()->checkExists($name)) {
            $service = $this->getServiceFactory()->create($name);
        } else {
            $service = $this->getServiceFactory()->create($this->defaultRecordServiceName);
            $service->setEntityType($name);
        }

        return $service;
    }

    protected function getPseudoTransactionManager(): PseudoTransactionManager
    {
        return $this->getContainer()->get('pseudoTransactionManager');
    }

}
