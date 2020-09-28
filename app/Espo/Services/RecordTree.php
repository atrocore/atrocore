<?php

namespace Espo\Services;

use \Espo\ORM\Entity;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\NotFound;

class RecordTree extends Record
{
    const MAX_DEPTH = 2;

    private $seed = null;

    protected $subjectEntityType = null;

    protected $categoryField = null;

    public function __construct()
    {
        parent::__construct();
        if (!$this->subjectEntityType) {
            $this->subjectEntityType = substr($this->entityType, 0, strlen($this->entityType) -8 );
        }
    }

    public function getTree($parentId = null, $params = array(), $level = 0, $maxDepth = null)
    {
        if (!$maxDepth) {
            $maxDepth = self::MAX_DEPTH;
        }

        if ($level == self::MAX_DEPTH) {
            return null;
        }

        $selectParams = $this->getSelectParams($params);
        $selectParams['whereClause'][] = array(
            'parentId' => $parentId
        );

        if ($this->hasOrder()) {
            $selectParams['orderBy'] = [
                ['order', 'asc'],
                ['name', 'asc']
            ];
        } else {
            $selectParams['orderBy'] = [
                ['name', 'asc']
            ];
        }

        $filterItems = false;
        if ($this->checkFilterOnlyNotEmpty()) {
            $filterItems = true;
        }

        $collection = $this->getRepository()->find($selectParams);
        if (!empty($params['onlyNotEmpty']) || $filterItems) {
            foreach ($collection as $i => $entity) {
                if ($this->checkItemIsEmpty($entity)) {
                    unset($collection[$i]);
                }
            }
        }
        foreach ($collection as $entity) {
            $childList = $this->getTree($entity->id, $params, $level + 1, $maxDepth);
            $entity->set('childList', $childList);
        }

        return $collection;
    }

    protected function checkFilterOnlyNotEmpty()
    {
        if (!$this->getAcl()->checkScope($this->subjectEntityType, 'create')) {
            return true;
        }
    }

    protected function checkItemIsEmpty(Entity $entity)
    {
        if (!$this->categoryField) return false;

        $selectManager = $this->getSelectManager($this->subjectEntityType);

        $selectParams = $selectManager->getEmptySelectParams();
        $selectManager->applyInCategory($this->categoryField, $entity->id, $selectParams);
        $selectManager->applyAccess($selectParams);

        if ($this->getEntityManager()->getRepository($this->subjectEntityType)->findOne($selectParams)) {
            return false;
        }
        return true;
    }

    public function getTreeItemPath($parentId = null)
    {
        $arr = [];
        while (1) {
            if (empty($parentId)) {
                break;
            }
            $parent = $this->getEntityManager()->getEntity($this->entityType, $parentId);
            if ($parent) {
                $parentId = $parent->get('parentId');
                array_unshift($arr, $parent->id);
            } else {
                $parentId = null;
            }
        }
        return $arr;
    }

    protected function getSeed()
    {
        if (empty($this->seed)) {
            $this->seed = $this->getEntityManager()->getEntity($this->getEntityType());
        }
        return $this->seed;
    }

    protected function hasOrder()
    {
        $seed = $this->getSeed();
        if ($seed->hasField('order')) {
            return true;
        }
        return false;
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        parent::beforeCreateEntity($entity, $data);

        if (!empty($data->parentId)) {
            $parent = $this->getEntityManager()->getEntity($this->getEntityType(), $data->parentId);
            if (!$parent) {
                throw new Error("Tried to create tree item entity with not existing parent.");
            }
            if (!$this->getAcl()->check($parent, 'edit')) {
                throw new Forbidden();
            }
        }
    }

    public function updateEntity($id, $data)
    {
        if (!empty($data->parentId) && $data->parentId == $id) {
            throw new Forbidden();
        }

        return parent::updateEntity($id, $data);
    }

    public function linkEntity($id, $link, $foreignId)
    {
        if ($id == $foreignId ) {
            throw new Forbidden();
        }
        return parent::linkEntity($id, $link, $foreignId);
    }

    public function getLastChildrenIdList($parentId = null)
    {
        $selectParams = $this->getSelectManager($this->entityType)->getSelectParams([], true, true);
        $selectParams['whereClause'][] = array(
            'parentId' => $parentId
        );

        $idList = [];

        $includingRecords = false;
        if ($this->checkFilterOnlyNotEmpty()) {
            $includingRecords = true;
        }

        $collection = $this->getRepository()->find($selectParams);
        foreach ($collection as $entity) {
            $selectParams2 = $this->getSelectManager($this->entityType)->getSelectParams([], true, true);
            $selectParams2['whereClause'][] = array(
                'parentId' => $entity->id
            );
            if (!$this->getRepository()->count($selectParams2)) {
                $idList[] = $entity->id;
            } else {
                if ($includingRecords) {
                    $isNotEmpty = false;
                    foreach ($this->getRepository()->find($selectParams2) as $subEntity) {
                        if (!$this->checkItemIsEmpty($subEntity)) {
                            $isNotEmpty = true;
                            break;
                        }
                    }
                    if (!$isNotEmpty) {
                        $idList[] = $entity->id;
                    }
                }
            }
        }
        return $idList;
    }
}
