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

use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\Core\Utils\Language;
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class Translation extends Base
{
    protected string $cacheFilePath = 'data/translations.json';

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('module') === 'custom' && !$entity->isNew() && !$entity->get('isCustomized')) {
            $entity->set('isCustomized', true);
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (!empty($entity->_input) && $entity->isAttributeChanged('isCustomized') && !$entity->get('isCustomized')) {
            $this->refreshToDefault();
        } else {
            $this->refreshTimestamp($options);
        }
    }

    public function getPreparedTranslations(): array
    {
        if (!file_exists($this->cacheFilePath)) {
            $this->saveCacheFile($this->find()->toArray());
        }

        return json_decode(file_get_contents($this->cacheFilePath), true);
    }

    protected function saveCacheFile(array $data): void
    {
        $preparedTranslationData = [];
        foreach ($data as $record) {
            foreach ($this->getMetadata()->get('multilang.languageList', []) as $locale) {
                $row = [];
                $field = Util::toCamelCase(strtolower($locale));
                if (array_key_exists($field, $record) && $record[$field] !== null) {
                    $insideRow = [];

                    $hasDots = strpos($record['code'], '...') !== false;
                    $parts = explode('.', $record['code']);
                    if ($hasDots) {
                        array_pop($parts);
                        array_pop($parts);
                        array_pop($parts);
                        $parts[] = array_pop($parts) . '...';
                    }

                    $this->prepareTreeValue($parts, $insideRow, $record[$field]);
                    $row[$record['module']][$locale] = $insideRow;
                    $preparedTranslationData = Util::merge($preparedTranslationData, $row);
                }
            }
        }
        // remove normalize number key to remove __integer
        $this->normalizeIntegerKey($preparedTranslationData);
        file_put_contents($this->cacheFilePath, json_encode($preparedTranslationData));
    }

    public function refreshToDefault(): void
    {
        $records = $this->getSimplifiedTranslates((new Language($this->getInjection('container')))->getModulesData());

        $existingMap = $this->fetchNonCustomizedCodeIdMap();

        $toInsert = [];
        $toUpdate = [];

        foreach ($records as $key => $row) {
            if (isset($existingMap[$key])) {
                $row['id'] = $existingMap[$key];
                $toUpdate[] = $row;
            } else {
                $row['id'] = IdGenerator::uuid();
                $toInsert[] = $row;
            }
        }

        $orphanedIds = array_values(array_diff_key($existingMap, $records));
        foreach (array_chunk($orphanedIds, 1000) as $chunk) {
            $this->getDbal()->createQueryBuilder()
                ->delete($this->getDbal()->quoteIdentifier('translation'))
                ->where('id IN (:ids)')
                ->setParameter('ids', $chunk, $this->getDbal()::PARAM_STR_ARRAY)
                ->executeQuery();
        }

        foreach (array_chunk($toUpdate, 1000) as $rows) {
            $this->bulkUpdate($rows);
        }

        foreach (array_chunk($toInsert, 1000) as $rows) {
            $this->bulkInsert($rows);
        }

        $this->refreshTimestamp([]);
    }

    private function fetchNonCustomizedCodeIdMap(): array
    {
        $rows = $this->getDbal()->createQueryBuilder()
            ->select('code', 'id')
            ->from($this->getDbal()->quoteIdentifier('translation'))
            ->where('is_customized = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        return array_column($rows, 'id', 'code');
    }

    private function bulkUpdate(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $allKeys = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $allKeys[$key] = true;
            }
        }
        $allKeys = array_keys($allKeys);

        $conn = $this->getDbal();
        $columns = array_map(fn(string $k) => Util::toUnderScore($k), $allKeys);
        $quotedColumns = array_map(fn(string $c) => $conn->quoteIdentifier($c), $columns);

        $rowPlaceholders = [];
        $params = [];
        $types = [];

        foreach ($rows as $row) {
            $placeholders = [];
            foreach ($allKeys as $key) {
                $value = $row[$key] ?? null;
                $placeholders[] = '?';
                $params[] = $value;
                if (is_bool($value)) {
                    $types[] = ParameterType::BOOLEAN;
                } elseif ($value === null) {
                    $types[] = ParameterType::NULL;
                } else {
                    $types[] = ParameterType::STRING;
                }
            }
            $rowPlaceholders[] = '(' . implode(', ', $placeholders) . ')';
        }

        $updateColumns = array_values(array_filter($columns, fn($c) => !in_array($c, ['id', 'created_at'])));

        if (Converter::isPgSQL($conn)) {
            $setClauses = array_map(
                fn($c) => $conn->quoteIdentifier($c) . ' = EXCLUDED.' . $conn->quoteIdentifier($c),
                $updateColumns
            );
            $onConflict = ' ON CONFLICT (' . $conn->quoteIdentifier('id') . ') DO UPDATE SET ' . implode(', ', $setClauses);
        } else {
            $setClauses = array_map(
                fn($c) => $conn->quoteIdentifier($c) . ' = VALUES(' . $conn->quoteIdentifier($c) . ')',
                $updateColumns
            );
            $onConflict = ' ON DUPLICATE KEY UPDATE ' . implode(', ', $setClauses);
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s%s',
            $conn->quoteIdentifier('translation'),
            implode(', ', $quotedColumns),
            implode(', ', $rowPlaceholders),
            $onConflict
        );

        $conn->executeStatement($sql, $params, $types);
    }

    private function bulkInsert(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        // Collect all unique keys across all rows (rows may have different column sets)
        $allKeys = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $allKeys[$key] = true;
            }
        }
        $allKeys = array_keys($allKeys);

        $conn = $this->getDbal();
        $quotedColumns = array_map(
            fn(string $key) => $conn->quoteIdentifier(Util::toUnderScore($key)),
            $allKeys
        );

        $rowPlaceholders = [];
        $params = [];
        $types = [];

        foreach ($rows as $row) {
            $placeholders = [];
            foreach ($allKeys as $key) {
                $value = $row[$key] ?? null;
                $placeholders[] = '?';
                $params[] = $value;
                if (is_bool($value)) {
                    $types[] = ParameterType::BOOLEAN;
                } elseif ($value === null) {
                    $types[] = ParameterType::NULL;
                } else {
                    $types[] = ParameterType::STRING;
                }
            }
            $rowPlaceholders[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $conn->quoteIdentifier('translation'),
            implode(', ', $quotedColumns),
            implode(', ', $rowPlaceholders)
        );

        $conn->executeStatement($sql, $params, $types);
    }

    public function getSimplifiedTranslates(array $data): array
    {
        $records = [];
        foreach ($data as $module => $moduleData) {
            foreach ($moduleData as $locale => $localeData) {
                $preparedLocaleData = [];
                self::toSimpleArray($localeData, $preparedLocaleData);
                foreach ($preparedLocaleData as $key => $value) {
                    $records[$key]['code'] = $key;
                    $records[$key]['module'] = $module;
                    $records[$key]['isCustomized'] = $module === 'custom';
                    $records[$key]['createdAt'] = date('Y-m-d H:i:s');
                    $records[$key][Util::toCamelCase(strtolower($locale))] = $value;
                }
            }
        }

        return $records;
    }

    public static function toSimpleArray(array $data, array &$result, array &$parents = []): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $parents[] = $key;
                self::toSimpleArray($value, $result, $parents);
            } else {
                $result[implode('.', array_merge($parents, [$key]))] = $value;
            }
        }

        if (!empty($parents)) {
            array_pop($parents);
        }
    }

    protected function normalizeIntegerKey(array &$data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($data[$key])) {
                $this->normalizeIntegerKey($data[$key]);
            }
            if (is_string($key) && str_starts_with($key, '__integer__')) {
                $realKey = str_replace('__integer__', '', $key);
                $data[$realKey] = $data[$key];;
                unset($data[$key]);
            }
        }
    }

    protected function prepareTreeValue(array $data, &$result, $value): void
    {
        if (!empty($data)) {
            $first = array_shift($data);
            if (!empty($data)) {
                $this->prepareTreeValue($data, $result[$first], $value);
            } else {
                if (preg_match('/^[0-9]+$/', $first)) {
                    $first = '__integer__' . $first;
                }
                $result[$first] = $value;
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->refreshTimestamp($options);
    }

    protected function refreshTimestamp(array $options): void
    {
        if (!empty($options['keepCache'])) {
            return;
        }

        $this->getInjection('language')->clearCache();

        $this->getConfig()->set('cacheTimestamp', time());
        $this->getConfig()->save();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('language');
    }
}
