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

namespace Atro\Repositories;

use Atro\Core\DataManager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Conflict;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity as OrmEntity;

class EntityUniqueIndex extends ReferenceData
{
    public const ALLOWED_ENTITY_TYPES = ['Base', 'Hierarchy'];

    public const NAME_PATTERN = '/^[a-z]([a-z0-9_]*[a-z0-9])?$/';

    /**
     * The `deleted` column is always the first column of the index.
     */
    public const DELETED_COLUMN = 'deleted';

    /**
     * The primary key is unique on its own, so an index containing it makes no sense.
     */
    public const FORBIDDEN_FIELDS = ['id', 'deleted'];

    protected array $ormFieldsCache = [];
    protected array $customIndexesCache = [];

    protected function getEntityById($id)
    {
        [$entityName, $indexName] = array_pad(explode('_', (string)$id, 2), 2, null);

        if (empty($entityName) || empty($indexName)) {
            return null;
        }

        $item = $this->prepareItem($entityName, $indexName);
        if (empty($item)) {
            return null;
        }

        $entity = $this->entityFactory->create($this->entityName);
        $entity->set($item);
        $entity->setAsFetched();

        return $entity;
    }

    protected function prepareItem(string $entityName, string $indexName, ?array $columns = null): ?array
    {
        if (!$this->isEntityAllowed($entityName)) {
            return null;
        }

        if ($columns === null) {
            $columns = $this->getMetadata()->get(['entityDefs', $entityName, 'uniqueIndexes', $indexName]);
        }

        if (empty($columns) || !is_array($columns)) {
            return null;
        }

        $fields = [];
        foreach ($columns as $column) {
            if ($column === self::DELETED_COLUMN) {
                continue;
            }
            $fields[] = $this->columnToField($entityName, (string)$column);
        }

        return [
            'id'         => "{$entityName}_{$indexName}",
            'code'       => $indexName,
            'name'       => $indexName,
            'fields'     => $fields,
            'entityId'   => $entityName,
            'entityName' => $this->translate($entityName, 'scopeNames'),
            'isCustom'   => array_key_exists($indexName, $this->getCustomIndexes($entityName))
        ];
    }

    protected function getAllItems(array $params = []): array
    {
        $entityName = null;
        foreach ($params['whereClause'] ?? [] as $item) {
            if (!empty($item['entityId='])) {
                $entityName = $item['entityId='];
            } elseif (!empty($item['entityId'])) {
                $entityName = $item['entityId'];
            }
        }

        if (!empty($entityName)) {
            $entities = is_array($entityName) ? $entityName : [$entityName];
        } else {
            $entities = array_keys($this->getMetadata()->get('scopes', []));
        }

        $items = [];
        foreach ($entities as $scope) {
            if (!$this->isEntityAllowed((string)$scope)) {
                continue;
            }

            foreach ($this->getMetadata()->get(['entityDefs', $scope, 'uniqueIndexes'], []) as $indexName => $columns) {
                if (!empty($item = $this->prepareItem((string)$scope, (string)$indexName, $columns))) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    public function validateCode(OrmEntity $entity): void
    {
    }

    public function validateUnique(OrmEntity $entity): void
    {
    }

    protected function beforeSave(OrmEntity $entity, array $options = [])
    {
        $entityName = (string)$entity->get('entityId');
        $indexName = (string)$entity->get('name');

        if (!$this->isEntityAllowed($entityName)) {
            throw new BadRequest($this->translateException('entityTypeIsNotSuitableForUniqueIndexes'));
        }

        if (!preg_match(self::NAME_PATTERN, $indexName)) {
            throw new BadRequest($this->translateException('indexNameIsInvalid'));
        }

        $this->validateNameIsFree($entity);

        $fields = $entity->get('fields');
        if (!is_array($fields) || count($fields) < 2) {
            throw new BadRequest($this->translateException('atLeastTwoFieldsRequired'));
        }

        if (count($fields) !== count(array_unique($fields))) {
            throw new BadRequest($this->translateException('indexFieldsShouldBeUnique'));
        }

        foreach ($fields as $field) {
            $fieldLabel = $this->translate((string)$field, 'fields', $entityName);

            if (in_array($field, self::FORBIDDEN_FIELDS, true) || !$this->isFieldTypeAllowed($entityName, (string)$field)) {
                throw new BadRequest(sprintf($this->translateException('fieldTypeCannotBeUsedInUniqueIndex'), $fieldLabel));
            }

            if ($this->fieldToColumn($entityName, (string)$field) === null) {
                throw new BadRequest(sprintf($this->translateException('fieldCannotBeUsedInUniqueIndex'), $fieldLabel));
            }
        }

        $this->validateMaxLength($entity);

        if ($entity->isNew() || $entity->isAttributeChanged('fields')) {
            $this->validateNoDuplicates($entityName, $this->getIndexColumns($entity));
        }

        $this->dispatch('beforeSave', $entity, $options);
    }

    /**
     * The index name is a part of the index name in the database, so it should be unique within the entity.
     */
    protected function validateNameIsFree(OrmEntity $entity): void
    {
        $entityName = (string)$entity->get('entityId');
        $indexName = (string)$entity->get('name');

        // the entity itself keeps its own name on update
        if (!$entity->isNew() && $entity->get('id') === "{$entityName}_{$indexName}") {
            return;
        }

        $taken = $this->getMetadata()->get(['entityDefs', $entityName, 'uniqueIndexes', $indexName]) !== null
            || $this->getMetadata()->get(['entityDefs', $entityName, 'indexes', $indexName]) !== null;

        if ($taken) {
            throw new Conflict(sprintf($this->translateException('indexNameAlreadyUsed'), $indexName));
        }
    }

    /**
     * The index cannot be created while the table contains records violating it,
     * so the duplicates are reported before the schema rebuild fails with a raw SQL error.
     */
    protected function validateNoDuplicates(string $entityName, array $columns): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $tableName = $this->getEntityManager()->getMapper()->toDb($entityName);

        $quoted = array_map(fn($column) => $connection->quoteIdentifier($column), $columns);

        $rows = $connection->createQueryBuilder()
            ->select(implode(', ', $quoted))
            ->from($connection->quoteIdentifier($tableName))
            ->groupBy(...$quoted)
            ->having('count(*) > 1')
            ->setMaxResults(16)
            ->fetchAllAssociative();

        if (empty($rows)) {
            return;
        }

        $examples = [];
        foreach (array_slice($rows, 0, 15) as $row) {
            unset($row[self::DELETED_COLUMN]);
            $examples[] = '(' . implode(', ', array_map(fn($value) => $value === null ? 'null' : (string)$value, $row)) . ')';
        }

        $str = implode(', ', $examples);
        if (count($rows) > 15) {
            $str .= ', ...';
        }

        throw new BadRequest(sprintf($this->translateException('duplicateValuesExist'), $str));
    }

    public function insertEntity(OrmEntity $entity): bool
    {
        $entityName = (string)$entity->get('entityId');
        $indexName = (string)$entity->get('name');

        $entity->id = "{$entityName}_{$indexName}";
        $entity->set('isCustom', true);

        $this->saveIndexToMetadata($entity);

        return true;
    }

    public function updateEntity(OrmEntity $entity): bool
    {
        if ($entity->isAttributeChanged('name')) {
            throw new BadRequest("Name cannot be changed.");
        }

        if ($entity->isAttributeChanged('entityId')) {
            throw new BadRequest("Entity cannot be changed.");
        }

        if (empty($entity->get('isCustom'))) {
            throw new Forbidden();
        }

        $this->saveIndexToMetadata($entity);

        return true;
    }

    protected function beforeRemove(OrmEntity $entity, array $options = [])
    {
        if (empty($entity->get('isCustom'))) {
            throw new Forbidden();
        }

        parent::beforeRemove($entity, $options);
    }

    public function deleteEntity(OrmEntity $entity): bool
    {
        $entityName = (string)$entity->get('entityId');
        $indexName = (string)$entity->get('name');

        $this->getMetadata()->delete('entityDefs', $entityName, ["uniqueIndexes.$indexName"]);
        $this->getMetadata()->save();

        unset($this->customIndexesCache[$entityName]);

        $this->getDataManager()->rebuild();

        return true;
    }

    protected function getIndexColumns(OrmEntity $entity): array
    {
        $entityName = (string)$entity->get('entityId');

        $columns = [self::DELETED_COLUMN];
        foreach ($entity->get('fields') as $field) {
            $columns[] = $this->fieldToColumn($entityName, (string)$field);
        }

        return $columns;
    }

    protected function saveIndexToMetadata(OrmEntity $entity): void
    {
        $entityName = (string)$entity->get('entityId');
        $indexName = (string)$entity->get('name');

        $columns = $this->getIndexColumns($entity);

        $this->getMetadata()->set('entityDefs', $entityName, [
            'uniqueIndexes' => [
                $indexName => $columns
            ]
        ]);
        $this->getMetadata()->save();

        unset($this->customIndexesCache[$entityName]);

        $this->getDataManager()->rebuild();
    }

    /**
     * Returns the names of the unique indexes the given field is a part of.
     */
    public function findIndexesByField(string $entityName, string $field): array
    {
        $column = $this->fieldToColumn($entityName, $field);
        if ($column === null) {
            return [];
        }

        $res = [];
        foreach ($this->getMetadata()->get(['entityDefs', $entityName, 'uniqueIndexes'], []) as $indexName => $columns) {
            if (is_array($columns) && in_array($column, $columns, true)) {
                $res[] = (string)$indexName;
            }
        }

        return $res;
    }

    protected function isEntityAllowed(string $entityName): bool
    {
        $scopeDefs = $this->getMetadata()->get(['scopes', $entityName]);

        if (empty($scopeDefs) || !empty($scopeDefs['emHidden'])) {
            return false;
        }

        return in_array($scopeDefs['type'] ?? null, self::ALLOWED_ENTITY_TYPES, true);
    }

    /**
     * Indexes defined in data/metadata are created by the user, so they can be changed and deleted.
     * Indexes coming from the module code are read-only.
     */
    protected function getCustomIndexes(string $entityName): array
    {
        if (!isset($this->customIndexesCache[$entityName])) {
            $indexes = [];

            $filePath = 'data/metadata/entityDefs/' . $entityName . '.json';
            if (file_exists($filePath)) {
                $data = @json_decode(file_get_contents($filePath), true);
                if (!empty($data['uniqueIndexes']) && is_array($data['uniqueIndexes'])) {
                    $indexes = $data['uniqueIndexes'];
                }
            }

            $this->customIndexesCache[$entityName] = $indexes;
        }

        return $this->customIndexesCache[$entityName];
    }

    /**
     * Only simple field types can be a part of the unique index. See `uniqueIndexAllowed` in the field type metadata.
     */
    protected function isFieldTypeAllowed(string $entityName, string $field): bool
    {
        $fieldType = $this->getMetadata()->get(['entityDefs', $entityName, 'fields', $field, 'type']);

        if (empty($fieldType)) {
            return false;
        }

        return !empty($this->getMetadata()->get(['fields', $fieldType, 'uniqueIndexAllowed']));
    }

    protected function getOrmFields(string $entityName): array
    {
        if (!isset($this->ormFieldsCache[$entityName])) {
            $this->ormFieldsCache[$entityName] = $this->getEntityManager()->getOrmMetadata()->get($entityName, 'fields') ?? [];
        }

        return $this->ormFieldsCache[$entityName];
    }

    /**
     * Converts a field name to the name of the table column, e.g. `classification` => `classification_id`.
     * Returns null if the field has no own column in the table.
     */
    protected function fieldToColumn(string $entityName, string $field): ?string
    {
        $ormFields = $this->getOrmFields($entityName);

        foreach ([$field, $field . 'Id'] as $ormField) {
            $defs = $ormFields[$ormField] ?? null;

            if (empty($defs) || !empty($defs['notStorable']) || ($defs['type'] ?? null) === 'foreign') {
                continue;
            }

            return Util::toUnderScore($ormField);
        }

        return null;
    }

    /**
     * Converts the name of the table column to a field name, e.g. `classification_id` => `classification`.
     */
    protected function columnToField(string $entityName, string $column): string
    {
        $ormField = Util::toCamelCase($column);
        $defs = $this->getOrmFields($entityName)[$ormField] ?? null;

        if (!empty($defs['originalName'])) {
            return (string)$defs['originalName'];
        }

        return $ormField;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }
}
