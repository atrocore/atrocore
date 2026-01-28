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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Utils\Language;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;
use Espo\Services\RecordService;

class Record extends RecordService
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        // prepare dates according to provided timezone
        if (!empty($_GET['timezone'])) {
            $this->modifyEntityFieldsByTimezone($entity, $_GET['timezone']);
        }

        if ($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'type']) !== 'ReferenceData') {
            foreach ($entity->entityDefs['fields'] as $field => $fieldDefs) {
                $fieldDefs = $entity->entityDefs['fields'][$field];

                if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'script' && !empty($fieldDefs['script'])
                    && $entity->has($field)
                    && $entity->get($field) === null
                ) {
                    $entity->_realtimeDisabled = true;
                    $this->getRepository()->calculateScriptFields($entity);
                    break;
                }
            }
        }
    }

    public function modifyEntityFieldsByTimezone(Entity $entity, string $timezone): void
    {
        $fields = $this->getMetadata()->get("entityDefs.{$entity->getEntityName()}.fields", []);
        foreach ($fields as $field => $fieldDefs) {
            if (empty($fieldDefs['type']) || !in_array($fieldDefs['type'], ['date', 'datetime'])) {
                continue;
            }
            if (empty($entity->get($field))) {
                continue;
            }

            try {
                $date = new \DateTime($entity->get($field));
                $date->setTimezone(new \DateTimeZone($timezone));
                $entity->set($field, $date->format('Y-m-d H:i:s'));
            } catch (\Throwable $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    public function massRemoveAttribute(array $attributes, array $params)
    {
        $params = $this
            ->dispatchEvent('beforeMassRemoveAttribute', new Event(['params' => $params, 'service' => $this]))
            ->getArgument('params');

        $params['action'] = 'removeAttribute';
        $params['maxCountWithoutJob'] = $this->getConfig()->get('massUpdateMaxCountWithoutJob', 200);
        $params['maxChunkSize'] = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);
        $params['minChunkSize'] = $this->getConfig()->get('massUpdateMinChunkSize', 400);
        $params['additionalJobData'] = ["attributes" => $attributes];

        list($count, $errors, $sync, $job) = $this->executeMassAction($params);

        return $this
            ->dispatchEvent('afterMassRemoveAttribute',
                new Event(['service' => $this, 'result' => ['count' => $count, 'sync' => false, 'jobId' => $job->get('id')]]))
            ->getArgument('result');
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function massRemove(array $params)
    {
        $params = $this
            ->dispatchEvent('beforeMassDelete', new Event(['params' => $params, 'service' => $this]))
            ->getArgument('params');

        $params['action'] = 'delete';
        $params['maxCountWithoutJob'] = $this->getConfig()->get('massDeleteMaxCountWithoutJob', 200);
        $params['maxChunkSize'] = $this->getConfig()->get('massDeleteMaxChunkSize', 3000);
        $params['minChunkSize'] = $this->getConfig()->get('massDeleteMinChunkSize', 400);

        if (!empty($params['permanently'])) {
            $callback = function ($id) {
                $this->deleteEntityPermanently($id);
            };
        } else {
            $callback = function ($id) {
                $this->deleteEntity($id);
            };
        }

        list($count, $errors, $sync) = $this->executeMassAction($params, $callback);

        return $this
            ->dispatchEvent('afterMassDelete',
                new Event(['service' => $this, 'result' => ['count' => $count, 'sync' => $sync, 'errors' => $errors]]))
            ->getArgument('result');
    }

    public function deleteEntityPermanently(string $id): bool
    {
        try {
            $deleted = $this->deleteEntity($id);
        } catch (NotFound $e) {
            if (empty($this->getRepository()->markedAsDeleted($id))) {
                throw new NotFound();
            }
            $deleted = true;
        }

        $res = false;

        if ($deleted) {
            $id = $this
                ->dispatchEvent('beforeDeleteEntityPermanently', new Event(['id' => $id, 'service' => $this]))
                ->getArgument('id');
            if (!empty($id)) {
                $this->getRepository()->deleteFromDb($id);
            }

            $res = true;
        }

        return $this
            ->dispatchEvent('afterDeleteEntityPermanently', new Event(['id' => $id, 'service' => $this, 'res' => $res]))
            ->getArgument('res');
    }

    public function executeMassAction(array $params, ?\Closure $actionOperation = null): array
    {
        if (empty($params['action']) || !is_int($params['maxCountWithoutJob']) || empty($params['maxChunkSize']) || empty($params['minChunkSize'])) {
            return [];
        }

        $action = $params['action'];
        $maxCountWithoutJob = $params['maxCountWithoutJob'];
        $maxChunkSize = $params['maxChunkSize'];
        $minChunkSize = $params['minChunkSize'];
        $maxConcurrentJobs = $this->getConfig()->get('maxConcurrentJobs', 6);

        if (!in_array($action, ['restore', 'delete', 'update', 'action', 'download', 'removeAttribute'])) {
            return [];
        }

        $actionEntity = null;
        if ($action === 'action' && !empty($params['additionalJobData']['actionId'])) {
            $actionEntity = $this->getEntityManager()->getEntity('Action', $params['additionalJobData']['actionId']);
            if (empty($actionEntity)) {
                return [];
            }
        }

        $repository = $this->getEntityManager()->getRepository($this->entityType);
        $byWhere = array_key_exists('where', $params);
        $errors = [];

        if (array_key_exists('ids', $params) && !empty($params['ids']) && is_array($params['ids'])) {
            $ids = $params['ids'];
            $total = count($ids);
        } elseif ($byWhere) {
            $selectParams = $this->getSelectParams(['where' => $params['where']], true, true);
            if ($action === 'delete' && !empty($params['permanently'])) {
                $selectParams['withDeleted'] = true;
            }

            $repository->handleSelectParams($selectParams);
            $total = $repository->count(array_merge($selectParams, ['select' => ['id']]));
        } else {
            $ids = [];
            $total = 0;
        }

        if ($total <= $maxCountWithoutJob && !empty($actionOperation)) {
            if ($byWhere) {
                $collection = $repository->find(array_merge($selectParams, ['select' => ['id']]));
                $ids = array_column($collection->toArray(), 'id');
            }
            foreach ($ids as $id) {
                try {
                    $actionOperation($id);
                } catch (\Throwable $e) {
                    $message = "{$action} {$this->getEntityType()} '$id' failed: {$e->getTraceAsString()}";
                    $GLOBALS['log']->error($message);
                    $entity = $this->getRepository()->get($id);
                    $name = !empty($entity) ? $entity->get('name') : $id;
                    $errors[] = "Error for '$name': {$e->getMessage()}";
                }
            }

            if (!empty($errors)) {
                $label = "mass" . ucfirst($action);
                $label .= count($errors) === count($ids) ? "NoRecordProceed" : "SomeRecordNotProceed";
                array_unshift($errors, $this->getInjection('language')->translate($label, 'exceptions'));
            }

            return [$total, $errors, true];
        } else {
            if ($total <= ($minChunkSize * $maxConcurrentJobs)) {
                $chunkSize = $minChunkSize;
            } else {
                if ($total >= ($minChunkSize * $maxConcurrentJobs) && $total <= ($maxChunkSize * $maxConcurrentJobs)) {
                    $chunkSize = ceil($total / $maxConcurrentJobs);
                } else {
                    $chunkSize = $maxChunkSize;
                }
            }

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'     => "Create jobs for mass " . (empty($actionEntity) ? $action : $actionEntity->get('name')),
                'type'     => 'MassActionCreator',
                'priority' => $this->entityType === 'Job' ? 300 : 100,
                'payload'  => [
                    'ids'        => $ids ?? [],
                    'action'     => $action,
                    'entityName' => $this->entityType,
                    'chunkSize'  => (int)$chunkSize,
                    'total'      => $total,
                    'params'     => $params,
                ]
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);

            return [$total, $errors, false, $jobEntity];
        }
    }

    public function getNameField(string $scope): string
    {
        $nameField = $this->getMetadata()->get(['scopes', $scope, 'nameField']);
        if (empty($nameField) || empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', $nameField]))) {
            $nameField = 'name';
        }
        return $nameField;
    }

    public function getLocalizedNameField(string $scope): ?string
    {
        $nameField = $this->getNameField($scope);
        $name = Language::getLocalizedFieldName($this->getEntityManager()->getContainer(), $scope, $nameField);

        if ($name !== $nameField) {
            return $name;
        }

        return null;
    }

    public function getLocalizedNameValue($record, string $scope): ?string
    {
        $nameField = $this->getNameField($scope);
        $localizedName = $this->getLocalizedNameField($scope);

        if (!empty($localizedName)) {
            $value = is_array($record) ? $record[$localizedName] : $record->get($localizedName);
            if (!empty($value)) {
                return (string)$value;
            }
        }

        return (string)(is_array($record) ? $record[$nameField] : $record->get($nameField));
    }

    public function getParamsForTree(string $link, string $scope, array $params): array
    {
        if (!empty($link) && $link !== '_self') {
            $foreignLink = '';
            foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links']) ?? [] as $linkName => $linkData) {
                if (!empty($linkData['foreign']) && $linkData['foreign'] === $link && $linkData['entity'] === $scope) {
                    $foreignLink = $linkName;
                    break;
                }
            }

            if ($this->getMetadata()->get(['scopes', $this->entityName, 'type']) === 'ReferenceData') {
                $field = $link . 'Id';
                $foreignRepository = $this->getEntityManager()->getRepository($scope);

                $params['foreignWhere'][] = [
                    'type'      => 'isNotNull',
                    'attribute' => $field,
                ];

                $sp = $this->getSelectManager($scope)->getSelectParams(['where' => $params['foreignWhere']], true, true);
                $sp['select'] = [$field];
                $qb1 = $foreignRepository->getMapper()->createSelectQueryBuilder($foreignRepository->get(), $sp);

                $ids = $qb1->distinct()->fetchFirstColumn();

                $params['where'][] = [
                    'type'      => 'in',
                    'attribute' => 'id',
                    'value'     => $ids,
                ];
            } else if (!empty($foreignLink)) {
                $where = [
                    'type'      => 'isLinked',
                    'attribute' => $foreignLink,
                ];
                if (!empty($params['foreignWhere'])) {
                    $where['type'] = 'linkedWith';
                    $where['subQuery'] = $params['foreignWhere'];
                }
                $params['where'][] = $where;
            } else {
                $field = $link . 'Id';
                if ($link === 'teams') {
                    // TODO:  apply main filter
                    $params['queryCallbacks'] = [
                        function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($scope) {
                            $ta = $mapper->getQueryConverter()->getMainTableAlias();

                            $qb->innerJoin($ta, 'entity_team', 'et', "$ta.id = et.team_id")
                                ->innerJoin('et', $mapper->toDb($scope), 'ts', "et.entity_id = ts.id")
                                ->andWhere("et.entity_id is not null")
                                ->andWhere("et.deleted = :false")
                                ->andWhere("ts.deleted = :false")
                                ->setParameter('false', false, ParameterType::BOOLEAN);
                        }
                    ];
                    $params['distinct'] = true;
                } else if (!empty($this->getEntityManager()->getOrmMetadata()->get($scope, 'fields')[$field])) {
                    if (!empty($params['foreignWhere'])) {
                        $params['where'][] = [
                            'type'          => 'in',
                            'attribute'     => 'id',
                            'subQuery'      => $params['foreignWhere'],
                            'foreignEntity' => $scope,
                            'foreignField'  => $field,
                        ];
                    } else {
                        $params['queryCallbacks'] = [
                            function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($field, $scope) {
                                $ta = $mapper->getQueryConverter()->getMainTableAlias();
                                $column = $mapper->toDb($field);

                                $qb->leftJoin($ta, $mapper->toDb($scope), 'et', "$ta.id = et.$column")
                                    ->andWhere("et.$column is not null")
                                    ->andWhere("et.deleted = :false")
                                    ->setParameter('false', false, ParameterType::BOOLEAN);
                            }
                        ];
                        $params['distinct'] = true;
                    }
                } else {
                    throw new BadRequest("Field $field not found on $scope and Foreign link not found for ($scope: $link) on " . $this->entityName);
                }
            }
        }
        unset($params['foreignWhere']);


        return $params;
    }

    public function getTreeItems(string $link, string $scope, array $params): array
    {
        $params = $this->getParamsForTree($link, $scope, $params);

        $repository = $this->getRepository();

        $selectParams = $this->getSelectManager($this->entityType)->getSelectParams($params, true, true);
        if (!empty($params['distinct'])) {
            $selectParams['distinct'] = true;
        }

        $fields = ['id', $this->getNameField($this->entityName)];
        $localizedNameField = $this->getLocalizedNameField($this->entityName);
        if (!empty($localizedNameField)) {
            $fields[] = $localizedNameField;
        }


        if (!empty($selectParams['orderBy']) && !in_array($selectParams['orderBy'], $fields)) {
            $fields[] = $selectParams['orderBy'];
        }
        $selectParams['select'] = $fields;
        $collection = $repository->find($selectParams);
        $total = $repository->count($selectParams);
        $offset = $params['offset'];
        $result = [];

        foreach ($collection as $key => $item) {
            $value = $this->getLocalizedNameValue($item, $this->entityName);
            $result[] = [
                'id'             => $item->get('id'),
                'name'           => !empty($value) ? $value : $item->get('id'),
                'offset'         => $offset + $key,
                'total'          => $total,
                'disabled'       => false,
                'load_on_demand' => false,
                'scope'          => $this->entityName,
            ];
        }

        return [
            'list'  => $result,
            'total' => $total
        ];
    }

    public function merge($id, array $sourceIdList, \stdClass $attributes, bool $keepSources = false)
    {
        $repository = $this->getRepository();

        if (!empty($id)) {
            $entity = $this->getEntityManager()->getEntity($this->getEntityType(), $id);
        } else {
            $input = $attributes->input;
            unset($input->id);
            $entity = $this->createEntity($input);
        }

        if (!$entity) {
            throw new NotFound();
        }

        $relationshipData = json_decode(json_encode($attributes->relationshipData), true);

        $sourceList = array();
        foreach ($sourceIdList as $sourceId) {
            if (is_object($sourceId)) {
                $source = $sourceId;
                $sourceList[] = $source;
                continue;
            }
            $source = $this->getEntity($sourceId);
            $sourceList[] = $source;
        }

        $this->beforeMerge($entity, $sourceList, $attributes);

        if (empty($keepSources)) {
            $connection = $this->getEntityManager()->getConnection();

            $types = ['Post', 'EmailSent', 'EmailReceived'];

            foreach ($sourceList as $source) {
                $connection->createQueryBuilder()
                    ->update($connection->quoteIdentifier('note'), 'n')
                    ->set('parent_id', ':entityId')
                    ->set('parent_type', ':entityType')
                    ->where('n.type IN (:types)')
                    ->andWhere('n.parent_id = :sourceId')
                    ->andWhere('n.parent_type = :sourceType')
                    ->andWhere('n.deleted = :false')
                    ->setParameter('entityId', $entity->id)
                    ->setParameter('entityType', $entity->getEntityType())
                    ->setParameter('types', $types, Mapper::getParameterType($types))
                    ->setParameter('sourceId', $source->id)
                    ->setParameter('sourceType', $source->getEntityType())
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->executeQuery();
            }
        }

        foreach ($this->getMergeLinkList($relationshipData) as $link) {
            $method = 'applyMergeFor' . ucfirst($link);
            if (method_exists($this, $method)) {
                $this->$method($entity, $sourceList);
                continue;
            }

            foreach ($sourceList as $source) {
                $linkedList = [];
                if (!empty($source->get('__relationships'))) {
                    $linkedIds = $source->get('__relationships')[$link]['ids'] ?? [];
                    $foreignScope = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links', $link, 'entity']);
                    if (!empty($linkedIds) && !empty($foreignScope)) {
                        $linkedList = $this->getEntityManager()->getRepository($foreignScope)->findByIds($linkedIds);
                    }
                } else {
                    if ($source->getEntityType() === $this->getEntityType()) {
                        $linkedList = $repository->findRelated($source, $link);
                    }
                }
                foreach ($linkedList as $linked) {
                    try {
                        $repository->relate($entity, $link, $linked);
                    } catch (UniqueConstraintViolationException $e) {
                    }
                }
            }
        }

        $upsertData = [];
        foreach ($relationshipData as $data) {
            if (empty($data['scope'])) {
                continue;
            }
            if (!empty($data['toUpsert'])) {
                foreach ($data['toUpsert'] as $payload) {
                    $input = new \stdClass();
                    $input->entity = $data['scope'];
                    $input->payload = (object)$payload;
                    $upsertData[] = $input;
                }
            }

            if (!empty($data['toDelete'])) {
                $this->getRecordService($data['scope'])->massRemove([
                    'ids' => $data['toDelete']
                ]);
            }
        }

        $this->getRecordService('MassActions')->upsert($upsertData);

        if (!empty($id)) {
            try {
                $attributes->input->_skipCheckForConflicts = true;
                $this->updateEntity($id, $attributes->input);
            } catch (NotModified $e) {

            }
        }

        if (empty($keepSources)) {
            foreach ($sourceList as $source) {
                $this->getEntityManager()->removeEntity($source);
            }
        }


        $this->afterMerge($entity, $sourceList, $attributes);

        return $entity;
    }

    public function getMergeLinkList(array $relationshipData): array
    {
        $mergeLinkList = [];
        $linksDefs = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links']);
        $customMergeLinkList = array_keys($relationshipData);
        $customEntityToMerge = array_column(array_values($relationshipData), 'scope');
        foreach ($linksDefs as $link => $d) {
            if (in_array($link, $this->getForbiddenLinksToMerge())) {
                continue;
            }

            if (in_array($link, $this->getMandatoryLinksToMerge())) {
                $mergeLinkList[] = $link;
                continue;
            }

            if (!empty($d['notMergeable'])) {
                continue;
            }

            if (in_array($link, $customMergeLinkList)) {
                continue;
            }

            if (!empty($d['type']) && in_array($d['type'], ['hasMany', 'hasChildren'])) {
                if (empty($d['entity']) || empty($d['foreign'])) {
                    continue;
                }

                if (in_array($d['entity'], $customEntityToMerge)) {
                    continue;
                }

                $mergeLinkList[] = $link;
            }
        }

        return $mergeLinkList;
    }

    protected function getMandatoryLinksToMerge(): array
    {
        return [];
    }

    protected function getForbiddenLinksToMerge(): array
    {
        $links = [];
        $scopeDefs = $this->getMetadata()->get(['scopes', $this->entityName]);
        if (!empty($scopeDefs['type']) && $scopeDefs['type'] === 'Hierarchy' && empty($scopeDefs['multiParents'])) {
            $links[] = 'parents';
        }
        return $links;
    }

    protected function getRequiredFields(Entity $entity, \stdClass $data): array
    {
        $event = $this->dispatchEvent('beforeGetRequiredFields', new Event(['entity' => $entity, 'data' => $data]));

        $res = parent::getRequiredFields($entity, $event->getArgument('data'));

        return $this
            ->dispatchEvent('afterGetRequiredFields', new Event(['entity' => $entity, 'data' => $data, 'result' => $res]))
            ->getArgument('result');
    }

    public function createFromPrimaryRecord(string $id, \stdClass $input): Entity
    {
        $primaryEntityId = $this->getMetadata()->get(['scopes', $this->getentityType(), 'primaryEntityId']);
        if (empty($primaryEntityId)) {
            throw new BadRequest("{$this->getEntityType()} is not of type 'Derivative'");
        }

        $primaryEntity = $this->getEntityManager()->getEntity($primaryEntityId, $id);
        if (empty($primaryEntity)) {
            throw new NotFound("{$this->getEntityType()} with id $id not found");
        }

        $input->primaryRecordId = $primaryEntity->get('id');

        foreach ($primaryEntity->toArray() as $field => $value) {
            if (in_array($field, ['id', 'createdAt', 'modifiedAt', 'createdBy', 'modifiedBy'])) {
                continue;
            }

            if (isset($input->$field)) {
                continue;
            }

            $input->$field = $value;
        }

        $entity = $this->createEntity($input);


        // create many-to-many relations
        foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links']) as $link => $linkDef) {
            if (!empty($linkDef['relationName'])) {
                $data = $primaryEntity->get($link) ?? [];
                foreach ($data as $item) {
                    try {
                        $this->getEntityManager()->getRepository($entity->getEntityType())->relate($entity, $link, $item, null);
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error($e->getMessage());
                    }
                }
            }
        }


        return $entity;
    }
}
