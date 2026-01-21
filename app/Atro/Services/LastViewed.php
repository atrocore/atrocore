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

namespace Atro\Services;

use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Metadata;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Repositories\UserProfile;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class LastViewed extends AbstractService
{
    protected function init()
    {
        parent::init();
        $this->addDependency('selectManagerFactory');
        $this->addDependency('metadata');
    }

    public function getLastVisitItemsTreeData($scope, $offset = 0): array
    {
        $params = [
            'maxSize'        => $this->getConfig()->get('recordsPerPageSmall', 20),
            'offset'         => $offset ?? 0,
            'skipDeleted'    => true,
            'targetTypeList' => [$scope]
        ];

        $data = $this->get($params);

        $result = [];
        $i = 0;
        foreach ($data['collection'] as $item) {
            $result[] = [
                'id'             => $item->get('targetId'),
                'name'           => $item->get('targetName') ?? $item->get('targetId'),
                'offset'         => $offset + $i,
                'total'          => $data['total'],
                'disabled'       => false,
                'load_on_demand' => false,
                'scope'          => $scope
            ];
            $i++;
        }
        return [
            'total' => $data['total'],
            'list'  => $result
        ];
    }

    public function getLastEntities(int $maxSize = 3, ?string $entityName = null, ?string $entityId = null, ?string $tabId = null): array
    {
        $scopes = $this->getMetadata()->get('scopes');

        $targetTypeList = array_filter(array_keys($scopes),
            fn($item) => !empty($scopes[$item]['entity']));

        $targetTypeList[] = 'App';

        /** @var \Espo\Core\SelectManagers\Base $selectManager */
        $selectManager = $this->getInjection('selectManagerFactory')->create('ActionHistoryRecord');
        $params = [
            'maxSize' => $maxSize,
            'sortBy'  => 'createdAt',
            'asc'     => false
        ];

        $sp = $selectManager->getSelectParams($params);
        $sp['select'] = ['controllerName', 'targetId', 'data'];
        $sp['callbacks'][] = function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($entityName, $entityId, $targetTypeList, $tabId) {
            $t = $mapper->getQueryConverter()->getMainTableAlias();
            $connection = $this->getEntityManager()->getConnection();

            $subQb = $connection->createQueryBuilder();
            $subQb
                ->select(
                    'a.id',
                    'a.controller_name',
                    'a.target_id',
                    'a.created_at',
                    "ROW_NUMBER() OVER (PARTITION BY a.controller_name, a.target_id ORDER BY a.created_at DESC, a.id DESC) AS rn"
                )
                ->from('action_history_record', 'a')
                ->where('a.user_id = :userId')
                ->andWhere('a.deleted = :false')
                ->andWhere('a.controller_name IN (:targetTypeList)')
                ->andWhere('a.data LIKE :data_history');

            if ($entityName && $entityId) {
                $subQb->andWhere('(a.controller_name <> :entityName OR a.target_id <> :entityId OR a.target_id IS NULL)');
                $qb->setParameter('entityId', $entityId);
                $qb->setParameter('entityName', $entityName);
            } else if ($entityName) {
                $subQb->andWhere('(a.controller_name <> :entityName OR a.target_id IS NOT NULL)');
                $qb->setParameter('entityName', $entityName);
            }

            $queryHeader = $tabId ? '%"Entity-History":"' . $tabId . '"%' : '%"Entity-History":%';

            $qb->join($t, "({$subQb->getSQL()})", 'sub', "sub.id = $t.id AND sub.rn = 1")
                ->setParameter('userId', $this->getUser()->id)
                ->setParameter('targetTypeList', $targetTypeList, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
                ->setParameter('data_history', $queryHeader)
                ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN);
        };

        /** @var \Atro\Core\ORM\Repositories\RDB $repository */
        $repository = $this->getEntityManager()->getRepository('ActionHistoryRecord');
        $entities = $repository->find($sp);

        foreach ($entities as $entity) {
            if ($this->getEntityManager()->hasRepository($entity->get('controllerName'))) {
                $repository = $this->getEntityManager()->getRepository($entity->get('controllerName'));

                $nameField = $this->getMetadata()->get(['scopes', $entity->get('controllerName'), 'nameField']) ?? 'name';
                if ($repository instanceof ReferenceData || $repository instanceof UserProfile) {
                    $foreignEntity = $repository->get($entity->get('targetId'));
                } else {
                    $foreignEntity = $repository->select(['id', $nameField])->where(['id' => $entity->get('targetId')])->findOne();
                }
                if (!empty($foreignEntity)) {
                    $entity->set('targetName', $foreignEntity->get($nameField));
                }
            }

            $data = $entity->get('data');
            if ($entity->get('controllerName') === 'App' && !empty($data?->request?->body?->url)) {
                $entity->fields['targetUrl'] = [
                    'type' => 'varchar'
                ];

                $entity->set('targetUrl', $data->request->body->url);
            }

            $entity->clear('data');
        }

        return [
            'total'      => $entities->count(),
            'collection' => $entities->toArray(),
        ];
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function get(array $params = [])
    {
        $maxSize = $params['maxSize'] ?? $this->getConfig()->get('lastViewedCount', 20);

        $offset = $params['offset'] ?? 0;


        if (!empty($params['targetTypeList'])) {
            $targetTypeList = $params['targetTypeList'];
        } else {
            $scopes = $this->getMetadata()->get('scopes');

            $targetTypeList = array_filter(array_keys($scopes), function ($item) use ($scopes) {
                return !empty($scopes[$item]['object']) && empty($scopes[$item]['hideLastViewed']);
            });
        }


        $collection = $this->getEntityManager()->getRepository('ActionHistoryRecord')->where(array(
            'userId'           => $this->getUser()->id,
            'controllerAction' => 'read',
            'controllerName'   => $targetTypeList
        ))
            ->order(3, true)
            ->limit($offset, $maxSize)
            ->select(['targetId', 'controllerName', 'max:createdAt'])
            ->groupBy(['targetId', 'controllerName'])
            ->find();

        $count = $this->getEntityManager()->getRepository('ActionHistoryRecord')->where(array(
            'userId'           => $this->getUser()->id,
            'controllerAction' => 'read',
            'controllerName'   => $targetTypeList
        ))->select([
            'targetId', 'controllerName'
        ])->groupBy([
            'targetId', 'controllerName'
        ])->find()->count();

        foreach ($collection as $i => $entity) {
            if ($this->getEntityManager()->hasRepository($entity->get('controllerName'))) {
                $repository = $this->getEntityManager()->getRepository($entity->get('controllerName'));

                $nameField = $this->getMetadata()->get(['scopes', $entity->get('controllerName'), 'nameField']) ?? 'name';
                if ($repository instanceof ReferenceData || $repository instanceof UserProfile) {
                    $foreignEntity = $repository->get($entity->get('targetId'));
                } else {
                    $foreignEntity = $repository->select(['id', $nameField])->where(['id' => $entity->get('targetId')])->findOne();
                }
                if (!empty($foreignEntity)) {
                    $entity->set('targetName', $foreignEntity->get($nameField));
                }else if(!empty($params['skipDeleted'])) {
                    $collection->offsetUnset($i);
                    continue;
                }
            }

            $entity->id = $offset + $i;
        }

        return array(
            'total'      => $count,
            'collection' => $collection
        );
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }
}