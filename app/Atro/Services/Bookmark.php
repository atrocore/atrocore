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

namespace Atro\Services;

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\EventManager\Event;
use Espo\ORM\EntityCollection;

class Bookmark extends Base
{
    public function findEntities($params)
    {
        $params['where'][] = [
            "attribute" => "userId",
            "type" => "equals",
            "value" => $this->getUser()->id
        ];

        $params['sortBy'] = "entityType";

        $result = parent::findEntities($params);
        $collection = $result['collection'];
        $count = $result['total'];

        $groupedCollections = [];

        foreach ($collection as $key => $item) {
            $item->_key = $key;
            $groupedCollections[$item->get('entityType')][$item->get('entityId')] = $item;
        }

        $result = [];

        foreach ($groupedCollections as $entityType => $items) {
            $hasName = !empty($this->getMetadata()->get(['entityDefs', $entityType, 'fields', 'name', 'type']));

            /** @var Connection $connection */
            $connection = $this->getEntityManager()->getConnection();
            if ($this->getMetadata()->get(['scopes', $entityType, 'type']) === 'ReferenceData') {
                $entityNames = $this->getReferenceDataBookmarkedEntities($entityType, array_keys($items));
                $entityNames = !empty($entityNames) ? $entityNames->toArray() : [];
            } else {
                $entityNames = $connection->createQueryBuilder()
                    ->select('id, deleted, ' . ($hasName ? 'name' : 'id as name'))
                    ->from($connection->quoteIdentifier(strtolower(Util::toUnderScore($entityType))))
                    ->where('id IN (:ids)')
                    ->setParameter('ids', array_keys($items), Connection::PARAM_STR_ARRAY)
                    ->fetchAllAssociative();
            }

            $entityNameByIds = [];
            foreach ($entityNames as $entityName) {
                $entityNameByIds[$entityName['id']] = $entityName;
            }

            foreach ($items as $entityId => $item) {
                if (!empty($entityNameByIds[$entityId])) {
                    $data = $entityNameByIds[$entityId];
                    if (!empty($data['deleted'])) {
                        $this->getEntityManager()->removeEntity($item);
                        unset($collection[$item->_key]);
                        unset($groupedCollections[$item->get('entityType')][$item->get('entityId')]);
                        unset($items[$entityId]);
                        $count--;
                        continue;
                    }
                    $item->set('entityName', $data['name']);
                } else {
                    $this->getEntityManager()->removeEntity($item);
                    unset($collection[$item->_key]);
                    unset($groupedCollections[$item->get('entityType')][$item->get('entityId')]);
                    unset($items[$entityId]);
                    $count--;
                }
            }

            $collectionArr = array_map(fn($item) => $item->toArray(), array_values($items));
            usort($collectionArr, function ($a, $b) {
                return strcmp($b['entityName'], $a['entityName']);
            });

            $result[$entityType] = [
                "collection" => $collectionArr,
                "key" => $entityType,
                "rowList" => array_column($collectionArr, 'id')
            ];
        }

        return [
            "total" => $count,
            "list" => array_values($result)
        ];
    }

    public function getBookmarkTree(string $scope, array $params): array
    {
        $repository = $this->getEntityManager()->getRepository($scope);
        $result = [];

        if ($this->getMetadata()->get(['scopes', $scope, 'type']) === 'ReferenceData') {

            $collection = $this->getReferenceDataBookmarkedEntities($scope);
            $index = 0;

            foreach ($collection as $item) {
                $result[] = [
                    'id' => $item->get('id'),
                    'name' => $item->get('name') ?? $item->get('id'),
                    'offset' => $index,
                    'total' => $collection->count(),
                    'disabled' => false,
                    'load_on_demand' => false
                ];
                $index++;
            }

            $total = $collection->count();

        } else {
            $params['where'][] = [
                'type' => 'bool',
                'value' => ['onlyBookmarked']
            ];

            $selectParams = $this->getSelectManager($scope)->getSelectParams($params, true, true);

            $selectParams['select'] = ['id', 'name'];
            $collection = $repository->find($selectParams);
            $total = $repository->count($selectParams);
            $offset = $params['offset'];
            $result = [];

            foreach ($collection as $key => $item) {
                $result[] = [
                    'id' => $item->get('id'),
                    'name' => $item->get('name') ?? $item->get('id'),
                    'offset' => $offset + $key,
                    'total' => $total,
                    'disabled' => false,
                    'load_on_demand' => false
                ];
            }
        }


        return [
            'list' => $result,
            'total' => $total
        ];
    }

    public function createEntity($attachment)
    {
        $data = new \stdClass();
        $data->entityId = $attachment->entityId;
        $data->entityType = $attachment->entityType;

        $attachment = $this
            ->dispatchEvent('beforeCreateEntity', new Event(['attachment' => $data, 'service' => $this]))
            ->getArgument('attachment');

        $entity = $this->getRepository()->get();
        $entity->set($attachment);

        if ($this->storeEntity($entity)) {
            $this->afterCreateEntity($entity, $attachment);
        }

        return $entity;
    }

    public function deleteEntity($id)
    {
        $entity = $this->getRepository()->get($id);

        if (!$entity) {
            throw new NotFound();
        }

        return $this->getRepository()->remove($entity, $this->getDefaultRepositoryOptions());
    }

    protected function getReferenceDataBookmarkedEntities(string $scope, ?array $entityIds = null): ?EntityCollection
    {
        if ($this->getMetadata()->get(['scopes', $scope, 'type']) !== 'ReferenceData') {
            return null;
        }

        if (empty($entityIds)) {
            $entityIds = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('entity_id')
                ->from('bookmark')
                ->where('entity_type = :scope')
                ->andWhere('user_id = :userId')
                ->andWhere('deleted = :false')
                ->setParameter('scope', $scope)
                ->setParameter('userId', $this->getUser()->id)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            $entityIds = array_column($entityIds, 'entity_id');
        }

        $collection = $this->getEntityManager()->getRepository($scope)->find();

        foreach ($collection as $key => $item) {
            if (in_array($item->get('id'), $entityIds)) {
                continue;
            }
            unset($collection[$key]);
        }
        return $collection;
    }

}
