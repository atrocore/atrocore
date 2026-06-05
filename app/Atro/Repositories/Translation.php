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
use Atro\Core\Utils\Util;
use Atro\Core\Utils\Language as LanguageUtil;
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class Translation extends Base
{
    private array $cachedCodes = [];

    public static function languageToField(string $language): string
    {
        return Util::toCamelCase(strtolower($language));
    }

    public function setTranslation(string $scope, string $category, string $name, string $value): void
    {
        $code = "$scope.$category.$name";

        $translation = $this->findByCode($code);
        if (empty($translation)) {
            $translation = $this->get();
            $translation->set('code', $code);
        }

        $language = LanguageUtil::detectLanguage($this->getConfig(), $this->getEntityManager()->getUser()->get('delegator'));

        $translation->set(self::languageToField($language), $value);
        $this->save($translation);

        $this->cachedCodes[$code] = $translation;
    }

    public function deleteTranslation(string $scope, string $category, string $name): void
    {
        $code = "$scope.$category.$name";

        $translation = $this->findByCode($code);

        if (!empty($translation)) {
            $this->getEntityManager()->removeEntity($translation);
            if (isset($this->cachedCodes[$code])) {
                unset($this->cachedCodes[$code]);
            }
        }
    }

    public function setTranslationOption(string $scope, string $field, string $name, string $value): void
    {
        $code = "$scope.options.$field.$name";

        $translation = $this->findByCode($code);
        if (empty($translation)) {
            $translation = $this->get();
            $translation->set('code', $code);
        }

        $language = LanguageUtil::detectLanguage($this->getConfig(), $this->getEntityManager()->getUser()->get('delegator'));

        $translation->set(self::languageToField($language), $value);
        $this->save($translation);

        $this->cachedCodes[$code] = $translation;
    }

    public function setTranslationOptions(string $scope, string $field, array $values): void
    {
        $prefix = "$scope.options.$field.";

        $existingRows = $this->getDbal()->createQueryBuilder()
            ->select('code')
            ->from($this->getDbal()->quoteIdentifier('translation'))
            ->where('code LIKE :prefix')
            ->andWhere('deleted = :false')
            ->setParameter('prefix', $prefix . '%')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();

        foreach ($existingRows as $code) {
            $parts = explode('.', $code);
            $name = $parts[3];
            if (!array_key_exists($name, $values)) {
                $this->deleteTranslationOption($scope, $field, $name);
            }
        }

        foreach ($values as $name => $value) {
            if (empty($value)) {
                continue;
            }
            $this->setTranslationOption($scope, $field, (string)$name, (string)$value);
        }
    }

    public function deleteTranslationOption(string $scope, string $field, string $name): void
    {
        $code = "$scope.options.$field.$name";

        $translation = $this->findByCode($code);

        if (!empty($translation)) {
            $this->getEntityManager()->removeEntity($translation);
            if (isset($this->cachedCodes[$code])) {
                unset($this->cachedCodes[$code]);
            }
        }
    }

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

        $this->refreshTimestamp($options);
    }

    public function getTranslation(string $scope, string $category, string $name): ?Entity
    {
        return $this->findByCode("$scope.$category.$name");
    }

    public function getOptionTranslation(string $scope, string $field, string $value): ?Entity
    {
        return $this->findByCode("$scope.options.$field.$value");
    }

    public function findByCode(string $code): ?Entity
    {
        if (!isset($this->cachedCodes[$code])) {
            $this->cachedCodes[$code] = $this->where(['code' => $code])->findOne();
        }

        return $this->cachedCodes[$code];
    }

    public function fetchExistingCodeMap(): array
    {
        $rows = $this->getDbal()->createQueryBuilder()
            ->select('code', 'id', 'is_customized')
            ->from($this->getDbal()->quoteIdentifier('translation'))
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['code']] = [
                'id'           => $row['id'],
                'isCustomized' => (bool)$row['is_customized'],
            ];
        }

        return $map;
    }

    public function bulkInsert(array $rows): void
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

    public function bulkUpdate(array $rows): void
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

    public function bulkDelete(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $this->getDbal()->createQueryBuilder()
            ->delete($this->getDbal()->quoteIdentifier('translation'))
            ->where('id IN (:ids)')
            ->setParameter('ids', $ids, $this->getDbal()::PARAM_STR_ARRAY)
            ->executeQuery();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->refreshTimestamp($options);
    }

    public function refreshTimestamp(array $options): void
    {
        if (!empty($options['keepCache'])) {
            return;
        }

        $this->getConfig()->set('cacheTimestamp', time());
        $this->getConfig()->save();
    }
}
