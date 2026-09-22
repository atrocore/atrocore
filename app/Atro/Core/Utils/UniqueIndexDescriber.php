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

namespace Atro\Core\Utils;

use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Turns a unique constraint violation reported by the database into the list of fields that caused it.
 *
 * The databases report the name of the violated index, and AtroCore builds unique indexes in two ways:
 * the `uniqueIndexes` of the entity get an explicit name (`IDX_PRODUCT_UNIQUE_SKU_BRAND`), while the `unique`
 * parameter of a single field produces an index named by Doctrine (`UNIQ_8C7365215E237E06EB3B4E33`).
 * Both are reproduced here from the metadata, so the failed connection is not queried again.
 *
 * PostgreSQL aborts the whole transaction on a constraint violation, and the translations are stored in the
 * database, so everything this class needs is loaded by prepare() before the record is saved.
 */
class UniqueIndexDescriber
{
    public const TECHNICAL_COLUMNS = ['deleted'];

    /** @var array<string, array<string, array>> entity type => index name in the database => columns */
    protected array $indexes = [];

    /** @var array<string, array> entity type => field labels and messages */
    protected array $translations = [];

    public function __construct(
        protected readonly EntityManager $entityManager,
        protected readonly Metadata      $metadata,
        protected readonly Language      $language
    ) {
    }

    /**
     * Loads the index map and the translations for the entity type. Has to be called before saving a record,
     * while the database connection is still usable.
     */
    public function prepare(string $entityType): void
    {
        if (isset($this->translations[$entityType])) {
            return;
        }

        $ormFields = $this->entityManager->getOrmMetadata()->get($entityType, 'fields') ?? [];

        $labels = [];
        foreach ($this->getUniqueIndexes($entityType) as $columns) {
            foreach ($columns as $column) {
                if (in_array($column, self::TECHNICAL_COLUMNS, true)) {
                    continue;
                }

                $field = $this->columnToField($ormFields, (string)$column);
                $labels[$field] = $this->language->translate($field, 'fields', $entityType);
            }
        }

        $this->translations[$entityType] = [
            'fields'               => $labels,
            'notUniqueValue'       => $this->language->translate('notUniqueValue', 'exceptions'),
            'notUniqueValueFields' => $this->language->translate('notUniqueValueFields', 'exceptions'),
        ];
    }

    /**
     * Builds the message shown to the user, falling back to the generic one when the index cannot be identified.
     */
    public function buildMessage(Entity $entity, string $dbMessage): string
    {
        $entityType = $entity->getEntityType();

        if (!isset($this->translations[$entityType])) {
            // prepare() was not called before saving, so the translations may already be unreachable
            try {
                $this->prepare($entityType);
            } catch (\Throwable $e) {
                return $dbMessage;
            }
        }

        $fields = $this->describe($entity, $dbMessage);

        if ($fields === null) {
            return $this->translations[$entityType]['notUniqueValue'];
        }

        return sprintf($this->translations[$entityType]['notUniqueValueFields'], $fields);
    }

    public function describe(Entity $entity, string $dbMessage): ?string
    {
        $entityType = $entity->getEntityType();

        $indexName = $this->extractIndexName($dbMessage);
        if ($indexName === null) {
            return null;
        }

        $columns = $this->getUniqueIndexes($entityType)[strtoupper($indexName)] ?? null;
        if (empty($columns)) {
            return null;
        }

        $ormFields = $this->entityManager->getOrmMetadata()->get($entityType, 'fields') ?? [];
        $labels = $this->translations[$entityType]['fields'] ?? [];

        $parts = [];
        foreach ($columns as $column) {
            if (in_array($column, self::TECHNICAL_COLUMNS, true)) {
                continue;
            }

            $field = $this->columnToField($ormFields, (string)$column);

            $parts[] = sprintf('%s (%s)', $labels[$field] ?? $field, $this->formatValue($entity, $field, Util::toCamelCase((string)$column)));
        }

        return empty($parts) ? null : implode(', ', $parts);
    }

    /**
     * Every unique index of the entity, keyed by the name it has in the database.
     */
    public function getUniqueIndexes(string $entityType): array
    {
        if (isset($this->indexes[$entityType])) {
            return $this->indexes[$entityType];
        }

        $result = [];

        foreach ($this->metadata->get(['entityDefs', $entityType, 'uniqueIndexes'], []) as $name => $columns) {
            if (is_array($columns)) {
                $result[strtoupper(Converter::generateIndexName($entityType, (string)$name))] = $columns;
            }
        }

        $tableName = Util::toUnderScore($entityType);
        $maxLength = $this->entityManager->getConnection()->getDatabasePlatform()->getMaxIdentifierLength();

        foreach ($this->getSingleFieldIndexes($entityType) as $columns) {
            $result[$this->generateDoctrineIndexName($tableName, $columns, $maxLength)] = $columns;
        }

        return $this->indexes[$entityType] = $result;
    }

    /**
     * The indexes the schema converter creates out of the `unique` field parameter and out of the one-to-one links.
     */
    protected function getSingleFieldIndexes(string $entityType): array
    {
        $entityDefs = $this->entityManager->getOrmMetadata()->get($entityType) ?? [];
        $hasDeleted = isset($entityDefs['fields']['deleted']);

        $result = [];
        foreach ($entityDefs['fields'] ?? [] as $fieldName => $fieldDefs) {
            if (!empty($fieldDefs['notStorable']) || empty($fieldDefs['type']) || $fieldDefs['type'] === 'foreign') {
                continue;
            }

            $originalName = $fieldDefs['originalName'] ?? null;

            $isUnique = !empty($fieldDefs['unique'])
                && !in_array($fieldDefs['type'], ['id', 'autoincrement'], true)
                && empty($fieldDefs['autoincrement']);

            $isOneToOne = $fieldDefs['type'] === 'foreignId'
                && ($entityDefs['relations'][$originalName]['type'] ?? null) === 'belongsTo'
                && ($entityDefs['relations'][$originalName]['relationType'] ?? null) === 'oneToOne';

            if (!$isUnique && !$isOneToOne) {
                continue;
            }

            $columns = [Converter::getColumnName($fieldName)];
            if ($hasDeleted) {
                $columns[] = 'deleted';
            }

            $result[] = $columns;
        }

        return $result;
    }

    /**
     * Reproduces the name Doctrine generates for an index created without an explicit name.
     */
    public function generateDoctrineIndexName(string $tableName, array $columnNames, int $maxLength): string
    {
        $hash = '';
        foreach (array_merge([$tableName], $columnNames) as $column) {
            $hash .= dechex(crc32((string)$column));
        }

        return strtoupper(substr('uniq_' . $hash, 0, $maxLength));
    }

    public function extractIndexName(string $message): ?string
    {
        // PostgreSQL: duplicate key value violates unique constraint "idx_foo_unique_name_price"
        if (preg_match('/unique constraint "([^"]+)"/i', $message, $matches)) {
            return $matches[1];
        }

        // MySQL and MariaDB: Duplicate entry 'Test 2-50' for key 'foo.IDX_FOO_UNIQUE_NAME_PRICE'
        if (preg_match('/for key [\'"`]([^\'"`]+)[\'"`]/i', $message, $matches)) {
            $parts = explode('.', $matches[1]);

            return (string)end($parts);
        }

        return null;
    }

    protected function columnToField(array $ormFields, string $column): string
    {
        $ormField = Util::toCamelCase($column);

        return $ormFields[$ormField]['originalName'] ?? $ormField;
    }

    protected function formatValue(Entity $entity, string $field, string $ormField): string
    {
        // a link is more readable by its name than by its id
        foreach ([$field . 'Name', $ormField, $field] as $attribute) {
            if ($entity->hasAttribute($attribute)) {
                $value = $entity->get($attribute);
                break;
            }
        }

        if (!isset($value) || $value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string)$value;
    }
}
