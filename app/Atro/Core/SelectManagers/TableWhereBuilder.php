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

namespace Atro\Core\SelectManagers;

use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Atro\ORM\DB\RDB\Query\QueryConverter;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Builds an SQL condition from a where item for a table that is not an entity, e.g. a satellite table of an entity.
 * Columns are addressed by their camelCase names, operators are the same as for entity fields.
 */
class TableWhereBuilder
{
    protected ColumnWhereConverter $columnWhereConverter;

    public function __construct(ColumnWhereConverter $columnWhereConverter)
    {
        $this->columnWhereConverter = $columnWhereConverter;
    }

    /**
     * Returns the condition for the table under $alias, or null if the item gives no condition.
     * Parameters of the condition are set to $qb.
     */
    public function build(QueryBuilder $qb, string $alias, array $item): ?string
    {
        if (empty($item['type'])) {
            return null;
        }

        $type = $item['type'];

        if ($type === 'and' || $type === 'or') {
            $parts = [];
            foreach ($item['value'] ?? [] as $subItem) {
                $part = $this->build($qb, $alias, $subItem);
                if ($part !== null) {
                    $parts[] = $part;
                }
            }

            return empty($parts) ? null : '(' . implode(' ' . strtoupper($type) . ' ', $parts) . ')';
        }

        if ($type === 'not') {
            $part = $this->build($qb, $alias, ['type' => 'and', 'value' => $item['value'] ?? []]);

            return $part === null ? null : "NOT COALESCE($part, FALSE)";
        }

        if ($type === 'innerSql') {
            return $this->addInnerSql($qb, $item['value']);
        }

        if (!empty($item['dateTime'])) {
            $dateTimeItem = $this->columnWhereConverter->prepareDateTimeItem($item);

            return $dateTimeItem === null ? null : $this->build($qb, $alias, $dateTimeItem);
        }

        $attribute = $item['attribute'] ?? $item['field'] ?? null;
        if (empty($attribute) || !$this->columnWhereConverter->isSupported($type)) {
            return null;
        }

        $where = $this->columnWhereConverter->convert($attribute, $type, $item['value'] ?? null, $alias);

        return $this->render($qb, $alias, $where);
    }

    /**
     * Renders an ORM where part the same way as QueryConverter does for entity fields.
     */
    protected function render(QueryBuilder $qb, string $alias, array $where, string $sqlOperator = 'AND'): ?string
    {
        $parts = [];
        foreach ($where as $key => $value) {
            if (is_int($key) || in_array($key, QueryConverter::$sqlOperators, true)) {
                $part = $this->render($qb, $alias, $value, is_int($key) ? 'AND' : $key);
                if ($part !== null) {
                    $parts[] = "($part)";
                }
                continue;
            }

            if ($key === 'innerSql') {
                $parts[] = $this->addInnerSql($qb, $value);
                continue;
            }

            $part = $this->renderComparison($qb, $alias, $key, $value);
            if ($part !== null) {
                $parts[] = $part;
            }
        }

        return empty($parts) ? null : implode(" $sqlOperator ", $parts);
    }

    protected function renderComparison(QueryBuilder $qb, string $alias, string $key, mixed $value): ?string
    {
        $field    = $key;
        $operator = '=';
        if (!preg_match('/^[a-z0-9]+$/i', $key)) {
            foreach (QueryConverter::$comparisonOperators as $op => $sqlOp) {
                if (str_contains($key, $op)) {
                    $field    = trim(str_replace($op, '', $key));
                    $operator = $sqlOp;
                    break;
                }
            }
        }

        $column = $alias . '.' . Util::toUnderScore($field);

        if (is_array($value)) {
            $not = $operator === '<>' ? 'NOT ' : '';

            if (empty($value)) {
                return $operator === '<>' ? '1 = 1' : '1 = 0';
            }

            if (isset($value['innerSql'])) {
                return "$column {$not}IN " . $this->addInnerSql($qb, $value['innerSql']);
            }

            return "$column {$not}IN (:" . $this->addParameter($qb, $value) . ")";
        }

        if ($value === null) {
            return match ($operator) {
                '='     => "$column IS NULL",
                '<>'    => "$column IS NOT NULL",
                default => null
            };
        }

        $param = $this->addParameter($qb, $value);

        if (in_array($operator, ['LIKE', 'NOT LIKE']) && str_contains((string)$value, '%')) {
            return "LOWER($column) $operator LOWER(:$param)";
        }

        return "$column $operator :$param";
    }

    protected function addInnerSql(QueryBuilder $qb, array $innerSql): string
    {
        foreach ($innerSql['parameters'] ?? [] as $name => $value) {
            $qb->setParameter($name, $value, Mapper::getParameterType($value));
        }

        return '(' . $innerSql['sql'] . ')';
    }

    protected function addParameter(QueryBuilder $qb, mixed $value): string
    {
        $name = 'tw_' . IdGenerator::unsortableId();
        $qb->setParameter($name, $value, Mapper::getParameterType($value));

        return $name;
    }
}
