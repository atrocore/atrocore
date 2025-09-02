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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Utils\Util;
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

    public function getLocalizedNameField(string $scope): ?string
    {
        if (!empty($languages = $this->getConfig()->get('inputLanguageList'))) {
            if (!empty($userLanguage = $this->getUser()->getLanguage())) {
                if (!empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', 'name', 'isMultilang'])) &&
                    in_array($userLanguage, $languages)) {
                    $localeNameField = Util::toCamelCase('name_' . strtolower($userLanguage));
                    if (!empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', $localeNameField]))) {
                        return $localeNameField;
                    }
                }
            }
        }

        return null;
    }

    public function getLocalizedNameValue($record, string $scope): ?string
    {
        $localizedName = $this->getLocalizedNameField($scope);

        if (!empty($localizedName)) {
            $value = is_array($record) ? $record[$localizedName] : $record->get($localizedName);
            if (!empty($value)) {
                return $value;
            }
        }

        return is_array($record) ? $record['name'] : $record->get('name');
    }

    public function getTreeItems(string $link, string $scope, array $params): array
    {
        if ($link !== '_self') {
            if (in_array($link, ['createdBy', 'modifiedBy'])) {
                $params['queryCallbacks'] = [
                    function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($link, $scope) {
                        $ta = $mapper->getQueryConverter()->getMainTableAlias();
                        $column = $mapper->toDb($link . 'Id');

                        $qb->leftJoin($ta, $mapper->toDb($scope), 'et', "$ta.id = et.$column")
                            ->andWhere("et.$column is not null")
                            ->andWhere("et.deleted = :false")
                            ->setParameter('false', false, ParameterType::BOOLEAN);;
                    }
                ];
                $params['distinct'] = true;
            } else {
                $foreignLink = '';
                foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links']) ?? [] as $linkName => $linkData) {
                    if (!empty($linkData['foreign']) && $linkData['foreign'] === $link && $linkData['entity'] === $scope) {
                        $foreignLink = $linkName;
                    }
                }
                if (empty($foreignLink)) {
                    throw new BadRequest("Foreign link not found for ($scope: $link) on " . $this->entityName);
                }
                $params['where'][] = [
                    'type'      => 'isLinked',
                    'attribute' => $foreignLink,
                ];
            }
        }

        $repository = $this->getRepository();

        $selectParams = $this->getSelectManager($this->entityType)->getSelectParams($params, true, true);
        if (!empty($params['distinct'])) {
            $selectParams['distinct'] = true;
        }

        $fields = ['id', 'name'];
        $localizedNameField = $this->getLocalizedNameField($scope);
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
            $value = $this->getLocalizedNameValue($item, $scope);
            $result[] = [
                'id'             => $item->get('id'),
                'name'           => !empty($value) ? $value : $item->get('id'),
                'offset'         => $offset + $key,
                'total'          => $total,
                'disabled'       => false,
                'load_on_demand' => false
            ];
        }

        return [
            'list'  => $result,
            'total' => $total
        ];
    }

    public function merge($id, array $sourceIdList, \stdClass $attributes)
    {
        if (empty($id)) {
            throw new Error();
        }

        $repository = $this->getRepository();

        $entity = $this->getEntityManager()->getEntity($this->getEntityType(), $id);

        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $relationshipData = json_decode(json_encode($attributes->relationshipData), true);

        $sourceList = array();
        foreach ($sourceIdList as $sourceId) {
            $source = $this->getEntity($sourceId);
            $sourceList[] = $source;
            if (!$this->getAcl()->check($source, 'edit') || !$this->getAcl()->check($source, 'delete')) {
                throw new Forbidden();
            }
        }

        $this->beforeMerge($entity, $sourceList, $attributes);

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


        foreach ($mergeLinkList as $link) {
            $method = 'applyMergeFor' . ucfirst($link);
            if (method_exists($this, $method)) {
                $this->$method($entity, $sourceList);
                continue;
            }

            foreach ($sourceList as $source) {
                $linkedList = $repository->findRelated($source, $link);
                foreach ($linkedList as $linked) {
                    $repository->relate($entity, $link, $linked);

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

        try {
            $attributes->input->_skipCheckForConflicts = true;
            $this->updateEntity($id, $attributes->input);
        } catch (NotModified $e) {

        }

        foreach ($sourceList as $source) {
            $this->getEntityManager()->removeEntity($source);
        }

        $this->afterMerge($entity, $sourceList, $attributes);

        return true;
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
}
