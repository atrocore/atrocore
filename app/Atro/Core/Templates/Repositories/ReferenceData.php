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

namespace Atro\Core\Templates\Repositories;

use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Language;
use Atro\ORM\DB\RDB\Query\QueryConverter;
use Espo\Core\Interfaces\Injectable;
use Espo\Core\Utils\File\Manager as FileManager;
use Atro\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;
use Espo\ORM\Repository;
use Espo\ORM\EntityFactory;
use Espo\ORM\EntityManager;

class ReferenceData extends Repository implements Injectable
{
    public const DIR_PATH = 'data/reference-data';

    protected array $dependencies = [];
    protected array $injections = [];
    protected FileManager $fileManager;

    protected string $filePath;

    /**
     * @var array Parameters to be used in further find operations.
     */
    protected array $listParams = [];

    /**
     * @var array Where clause array. To be used in further find operation.
     */
    protected array $whereClause = [];

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        parent::__construct($entityType, $entityManager, $entityFactory);

        $this->filePath = self::DIR_PATH . "/$this->entityName.json";

        $this->init();
    }

    public function hasDeletedRecordsToClear(): bool
    {
        return false;
    }

    public function clearDeletedRecords(): void
    {
    }

    public function where($param1 = [], $param2 = null): self
    {
        if (is_array($param1)) {
            $this->whereClause = $param1 + $this->whereClause;
        } else {
            if (!is_null($param2)) {
                $this->whereClause[$param1] = $param2;
            }
        }

        return $this;
    }

    public function order(string $field = 'id', string $direction = "ASC"): self
    {
        $this->listParams['orderBy'] = $field;
        $this->listParams['order'] = $direction;

        return $this;
    }

    public function limit(int $offset, int $limit): self
    {
        $this->listParams['offset'] = $offset;
        $this->listParams['limit'] = $limit;

        return $this;
    }

    public function select(array $select): self
    {
        $this->listParams['select'] = $select;

        return $this;
    }

    public function get($id = null)
    {
        if (empty($id)) {
            return $this->getNewEntity();
        }

        return $this->getEntityById($id);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($entity->get('code'))) {
            throw new BadRequest('Code is required.');
        }

        $this->validateCode($entity);
        $this->validateUnique($entity);
        $this->validateMaxLength($entity);

        $this->dispatch('beforeSave', $entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->dispatch('afterSave', $entity, $options);
    }

    public function validateCode(Entity $entity): void
    {
        if ($entity->isNew()) {
            foreach ($this->find() as $exist) {
                if ($exist->get('code') === $entity->get('code')) {
                    throw new NotUnique(sprintf($this->translate('notUniqueRecordField', 'exceptions'), 'code'));
                }
            }
        }
    }

    public function validateUnique(Entity $entity): void
    {
        $uniques = [];
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'fields']) as $field => $fieldDefs) {
            if (!empty($fieldDefs['unique'])) {
                $uniques[] = $field;
            }
        }

        $items = $this->getAllItems() ?? [];
        foreach ($items as $item) {
            foreach ($uniques as $unique) {
                if ($item['id'] !== $entity->get('id') && $item[$unique] === $entity->get($unique)) {
                    $fieldName = $this->translate($unique, 'fields', $this->entityName);
                    throw new NotUnique(sprintf($this->translate('notUniqueRecordField', 'exceptions'), $fieldName));
                }
            }
        }
    }

    public function validateMaxLength(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'fields']) as $field => $fieldDefs) {
            if (!empty($fieldDefs['maxLength'])) {
                $fieldValue = (string)$entity->get($field);
                $length = strlen($fieldValue);
                $maxLength = (int)$fieldDefs['maxLength'];

                if ($length > $maxLength) {
                    $fieldLabel = $this->getLanguage()->translate($field, 'fields', $entity->getEntityType());
                    throw new BadRequest(sprintf($this->getLanguage()->translate('maxLengthIsExceeded', 'exceptions', 'Global'), $fieldLabel, $maxLength, $length));
                }
            }
        }
    }

    public function insertEntity(Entity $entity): bool
    {
        $item = $entity->toArray();
        if (isset($item['deleted'])) {
            unset($item['deleted']);
        }

        $items = $this->getAllItems();
        $items[$entity->get('code')] = $item;

        return $this->saveDataToFile($items);
    }

    public function updateEntity(Entity $entity): bool
    {
        $items = $this->getAllItems();
        foreach ($items as $code => $item) {
            if ($item['id'] === $entity->get('id')) {
                unset($items[$code]);
                $items[$entity->get('code')] = $entity->toArray();
            }
        }

        return $this->saveDataToFile($items);
    }

    public function deleteEntity(Entity $entity): bool
    {
        $items = $this->getAllItems();

        $newItems = [];
        foreach ($items as $item) {
            if ($item['id'] !== $entity->get('id')) {
                $newItems[$item['code']] = $item;
            }
        }

        return $this->saveDataToFile($newItems);
    }

    public function save(Entity $entity, array $options = [])
    {
        $nowString = date('Y-m-d H:i:s');
        $user = $this->getEntityManager()->getUser();

        if ($entity->isNew()) {
            if (!$entity->has('id')) {
                $entity->set('id', $this->generateId());
            }

            if ($entity->hasAttribute('createdAt')) {
                $entity->set('createdAt', $nowString);
            }
            if ($entity->hasAttribute('createdById') && $user) {
                $entity->set('createdById', $user->get('id'));
            }
        }

        if ($entity->hasAttribute('modifiedAt')) {
            $entity->set('modifiedAt', $nowString);
        }

        if ($entity->hasAttribute('modifiedById') && $user) {
            $entity->set('modifiedById', $user->get('id'));
        }

        $entity->setAsBeingSaved();

        if (empty($options['skipBeforeSave']) && empty($options['skipAll'])) {
            $this->beforeSave($entity, $options);
        }

        if ($entity->isNew() && !$entity->isSaved()) {
            $result = $this->insertEntity($entity);
        } else {
            $result = $this->updateEntity($entity);
        }

        if ($result) {
            $entity->setIsSaved(true);

            if (empty($options['skipAfterSave']) && empty($options['skipAll'])) {
                $this->afterSave($entity, $options);
            }
            if ($entity->isNew()) {
                if (empty($options['keepNew'])) {
                    $entity->setIsNew(false);
                }
            } else {
                if ($entity->isFetched()) {
                    $entity->updateFetchedValues();
                }
            }
        }
        $entity->setAsNotBeingSaved();

        return $result;
    }

    public function generateId(): string
    {
        return IdGenerator::unsortableId();
    }

    protected function getNewEntity()
    {
        $entity = $this->entityFactory->create($this->entityName);
        $entity->setIsNew(true);
        $entity->populateDefaults();

        return $entity;
    }

    public function getEntityByCode(string $code): ?Entity
    {
        $items = $this->getAllItems();
        if (isset($items[$code])) {
            $entity = $this->entityFactory->create($this->entityName);
            $entity->set($items[$code]);
            $entity->setAsFetched();

            return $entity;
        }

        return null;
    }

    protected function getEntityById($id)
    {
        $items = $this->getAllItems();
        foreach ($items as $item) {
            if ($item['id'] === $id) {
                $entity = $this->entityFactory->create($this->entityName);
                $entity->set($item);
                $entity->setAsFetched();

                return $entity;
            }
        }

        return null;
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->dispatch('beforeRemove', $entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->dispatch('afterRemove', $entity, $options);
    }

    public function remove(Entity $entity, array $options = [])
    {
        $this->beforeRemove($entity, $options);
        $result = $this->deleteEntity($entity);
        if ($result) {
            $this->afterRemove($entity, $options);
        }

        return $result;
    }

    public function find(array $params = [])
    {
        $params = array_merge($params, $this->listParams);

        if (!empty($this->whereClause)) {
            $params['whereClause'][] = $this->whereClause;
        }

        $items = $this->getAllItems($params);
        $items = array_values($items);

        foreach ($params['whereClause'] ?? [] as $row) {
            if (!empty($row['AND'])) {
                $row = $row['AND'][0];
            }
            foreach ($row as $field => $value) {
                // skip if SQL operator
                if (in_array($field, QueryConverter::$sqlOperators)) {
                    continue;
                }

                // filter by * alias
                if (preg_match('/^(.+)\*$/', $field, $matches)) {
                    $search = str_replace('%', '', $value);
                    $items = array_filter($items, function ($item) use ($search, $matches) {
                        return isset($item[$matches[1]]) && preg_match('/^' . preg_quote($search, '/') . '/i', $item[$matches[1]]);
                    });
                    $items = array_values($items);
                    continue;
                }

                // skip if comparison operator. It will be allowed later
                foreach (QueryConverter::$comparisonOperators as $alias => $sqlOperator) {
                    if ($alias !== '=' && str_contains($field, $alias)) {
                        continue 2;
                    }
                }

                if (str_ends_with($field, '=')) {
                    $field = substr($field, 0, -1);
                }

                $filtered = [];
                foreach ($items as $item) {
                    if (
                        !array_key_exists($field, $item)
                        || $item[$field] === $value
                        || (is_array($value) && in_array($item[$field], $value))
                    ) {
                        $filtered[] = $item;
                    }
                }

                $items = $filtered;
            }
        }

        // text filter
        foreach ($params['whereClause'] ?? [] as $key => $row) {
            if (!empty($params['whereClause'][$key]['OR'])) {
                $filtered = [];

                foreach ($params['whereClause'][$key]['OR'] as $k => $v) {
                    $field = str_replace('*', '', $k);
                    $search = str_replace('%', '', $v);
                    foreach ($items as $item) {
                        if (!isset($item[$field])) {
                            continue;
                        }
                        if (!isset($filtered[$item['code']]) && is_string($item[$field]) && stripos($item[$field], $search) !== false) {
                            $filtered[$item['code']] = $item;
                        }
                    }
                }
                $items = array_values($filtered);
            }
        }

        // sort data
        if (!empty($params['orderBy'])) {
            usort($items, function ($a, $b) use ($params) {
                $field = $params['orderBy'];
                if (!array_key_exists($field, $a)) {
                    $a[$field] = null;
                }
                if (!array_key_exists($field, $b)) {
                    $b[$field] = null;
                }
                if (strtolower($params['order']) === 'desc') {
                    return $b[$field] <=> $a[$field];
                } else {
                    return $a[$field] <=> $b[$field];
                }
            });
        }

        // limit data
        if (isset($params['limit']) && isset($params['offset'])) {
            $prepared = [];
            foreach ($items as $k => $item) {
                if ($k >= $params['offset'] && count($prepared) < $params['limit']) {
                    $prepared[] = $item;
                }
            }
            $items = $prepared;
        }

        $collection = new EntityCollection($items, $this->entityName, $this->entityFactory);
        $collection->setAsFetched();

        $this->whereClause = [];
        $this->listParams = [];

        return $collection;
    }

    public function findRelated(Entity $entity, string $link, array $selectParams): EntityCollection
    {
        $relationType = $entity->getRelationType($link);
        $entityType = $entity->getRelationParam($link, 'entity');

        if ($relationType === IEntity::HAS_MANY) {
            $idsField = $link . 'Ids';
            if (!empty($entity->get($idsField))) {
                return $this->getEntityManager()->getrepository($entityType)
                    ->where(['id' => $entity->get($idsField)])->find($selectParams);
            }
        }

        return new EntityCollection();
    }

    public function countRelated(Entity $entity, string $relationName, array $params = []): int
    {
        return 0;
    }

    public function findByIds(array $ids)
    {
        $result = $this->getAllItems();
        $result = array_filter(array_values($result), fn($item) => in_array($item['id'], $ids));
        $collection = new EntityCollection($result, $this->entityName, $this->entityFactory);
        $collection->setAsFetched();

        return $collection;
    }

    public function findOne(array $params = [])
    {
        $collection = $this->limit(0, 1)->find($params);

        return $collection[0] ?? null;
    }

    protected function getAllItems(array $params = []): array
    {
        $items = [];
        if (file_exists($this->filePath)) {
            $data = @json_decode(file_get_contents($this->filePath), true);
            if (is_array($data)) {
                $items = $data;
            }
        }

        return $items;
    }

    public function getAll()
    {
        $collection = new EntityCollection(array_values($this->getAllItems()), $this->entityName, $this->entityFactory);
        $collection->setAsFetched();

        return $collection;
    }

    public function count(array $params)
    {
        if (isset($params['offset'])) {
            unset($params['offset']);
        }

        if (isset($params['limit'])) {
            unset($params['limit']);
        }

        return count($this->find($params));
    }

    protected function saveDataToFile(array $data): bool
    {
        if (!is_dir(self::DIR_PATH)) {
            mkdir(self::DIR_PATH);
        }

        return !is_bool(file_put_contents($this->filePath, json_encode($data)));
    }

    public function getCacheKey(string $id): string
    {
        return "entity_{$this->entityType}_{$id}";
    }

    protected function init()
    {
        $this->addDependency('config');
        $this->addDependency('metadata');
        $this->addDependency('eventManager');
        $this->addDependency('language');
    }

    public function getDependencyList()
    {
        return $this->dependencies;
    }

    public function inject($name, $object): void
    {
        $this->injections[$name] = $object;
    }

    protected function addDependency(string $name): void
    {
        $this->dependencies[] = $name;
    }

    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    protected function dispatch(string $action, Entity $entity, $options, $arg1 = null, $arg2 = null, $arg3 = null)
    {
        $event = new Event(
            [
                'entityType'     => $this->entityName,
                'entity'         => $entity,
                'options'        => $options,
                'relationName'   => $arg1,
                'relationParams' => $arg2,
                'relationData'   => $arg2,
                'foreign'        => $arg3,
            ]
        );

        // dispatch an event
        $this->getEventManager()->dispatch('Entity', $action, $event);
    }

    protected function getConfig(): Config
    {
        return $this->getInjection('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }

    protected function getEventManager(): Manager
    {
        return $this->getInjection('eventManager');
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('language');
    }

    protected function translate(string $label, ?string $category = 'labels', ?string $scope = 'Global'): string
    {
        return $this->getLanguage()->translate($label, $category, $scope);
    }

    protected function translateException(string $key): string
    {
        return $this->translate($key, 'exceptions', $this->entityName);
    }
}
