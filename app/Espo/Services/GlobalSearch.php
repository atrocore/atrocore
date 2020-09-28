<?php

namespace Espo\Services;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\NotFound;

use Espo\ORM\Entity;

class GlobalSearch extends \Espo\Core\Services\Base
{
    protected function init()
    {
        parent::init();
        $this->addDependencyList([
            'entityManager',
            'user',
            'metadata',
            'acl',
            'selectManagerFactory',
            'config'
        ]);
    }

    protected function getSelectManagerFactory()
    {
        return $this->injections['selectManagerFactory'];
    }

    protected function getEntityManager()
    {
        return $this->injections['entityManager'];
    }

    protected function getAcl()
    {
        return $this->injections['acl'];
    }

    protected function getMetadata()
    {
        return $this->injections['metadata'];
    }

    public function find($query, $offset, $maxSize)
    {
        $entityTypeList = $this->getConfig()->get('globalSearchEntityList');

        $hasFullTextSearch = false;

        $relevanceSelectPosition = 0;

        $unionPartList = [];
        foreach ($entityTypeList as $entityType) {
            if (!$this->getAcl()->checkScope($entityType, 'read')) {
                continue;
            }
            if (!$this->getMetadata()->get(['scopes', $entityType])) {
                continue;
            }

            $selectManager = $this->getSelectManagerFactory()->create($entityType);

            $params = [
                'select' => ['id', 'name', ['VALUE:' . $entityType, 'entityType']]
            ];

            $fullTextSearchData = $selectManager->getFullTextSearchDataForTextFilter($query);

            if ($fullTextSearchData) {
                $hasFullTextSearch = true;
                $params['select'][] = [$fullTextSearchData['where'], '_relevance'];
            } else {
                $params['select'][] = ['VALUE:1.1', '_relevance'];
                $relevanceSelectPosition = count($params['select']);
            }

            $selectManager->manageAccess($params);
            $params['useFullTextSearch'] = true;
            $selectManager->applyTextFilter($query, $params);

            $sql = $this->getEntityManager()->getQuery()->createSelectQuery($entityType, $params);

            $unionPartList[] = '' . $sql . '';
        }
        if (empty($unionPartList)) {
            return [
                'total' => 0,
                'list' => []
            ];
        }

        $pdo = $this->getEntityManager()->getPDO();

        $unionSql = implode(' UNION ', $unionPartList);
        $countSql = "SELECT COUNT(*) AS 'COUNT' FROM ({$unionSql}) AS c";
        $sth = $pdo->prepare($countSql);
        $sth->execute();
        $row = $sth->fetch(\PDO::FETCH_ASSOC);
        $totalCount = $row['COUNT'];

        if (count($entityTypeList)) {
            $entityListQuoted = [];
            foreach ($entityTypeList as $entityType) {
                $entityListQuoted[] = $pdo->quote($entityType);
            }
            if ($hasFullTextSearch) {
                $unionSql .= " ORDER BY " . $relevanceSelectPosition . " DESC, FIELD(entityType, ".implode(', ', $entityListQuoted)."), name";
            } else {
                $unionSql .= " ORDER BY FIELD(entityType, ".implode(', ', $entityListQuoted)."), name";
            }
        } else {
            $unionSql .= " ORDER BY name";
        }

        $unionSql .= " LIMIT :offset, :maxSize";

        $sth = $pdo->prepare($unionSql);

        $sth->bindParam(':offset', $offset, \PDO::PARAM_INT);
        $sth->bindParam(':maxSize', $maxSize, \PDO::PARAM_INT);
        $sth->execute();
        $rows = $sth->fetchAll(\PDO::FETCH_ASSOC);

        $entityDataList = [];

        foreach ($rows as $row) {
            $entity = $this->getEntityManager()->getEntity($row['entityType'], $row['id']);
            $entityData = $entity->toArray();
            $entityData['_scope'] = $entity->getEntityType();
            $entityDataList[] = $entityData;
        }

        return array(
            'total' => $totalCount,
            'list' => $entityDataList,
        );
    }
}

