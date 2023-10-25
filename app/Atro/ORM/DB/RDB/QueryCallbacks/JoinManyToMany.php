<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\ORM\DB\RDB\QueryCallbacks;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class JoinManyToMany
{
    protected IEntity $entity;
    protected string $relationName;
    protected array $keySet;

    public function __construct(IEntity $entity, string $relationName, array $keySet)
    {
        $this->entity = $entity;
        $this->relationName = $relationName;
        $this->keySet = $keySet;
    }

    public function run(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $queryConverter = $mapper->getQueryConverter();

        $entity = $this->entity;
        $relationName = $this->relationName;
        $keySet = $this->keySet;

        $relOpt = $entity->relations[$relationName];

        $key = $keySet['key'];
        $foreignKey = $keySet['foreignKey'];
        $nearKey = $keySet['nearKey'];
        $distantKey = $keySet['distantKey'];

        $relTable = $mapper->toDb($relOpt['relationName']);
        $relAlias = "{$relTable}_mm";
        $alias = $queryConverter::TABLE_ALIAS;

        $condition = "{$alias}.{$mapper->toDb($foreignKey)} = {$relAlias}.{$mapper->toDb($distantKey)}";

        $condition .= " AND {$relAlias}.{$mapper->toDb($nearKey)} = :{$key}_mm1";
        $qb->setParameter("{$key}_mm1", $entity->get($key), Mapper::getParameterType($entity->get($key)));

        $condition .= " AND {$relAlias}.deleted = :deleted_mm2";
        $qb->setParameter("deleted_mm2", false, Mapper::getParameterType(false));

        if (!empty($relOpt['conditions']) && is_array($relOpt['conditions'])) {
            foreach ($relOpt['conditions'] as $f => $v) {
                $condition .= " AND {$relAlias}.{$mapper->toDb($f)} = :{$f}_mm3";
                $qb->setParameter("{$f}_mm3", $v, Mapper::getParameterType($v));
            }
        }

        foreach ($params['additionalColumnsConditions'] ?? [] as $f => $v) {
            $condition .= " AND {$relAlias}.{$mapper->toDb($f)} = :{$f}_mm4";
            $qb->setParameter("{$f}_mm4", $v, Mapper::getParameterType($v));
        }

        $qb->innerJoin($alias, $queryConverter->quoteIdentifier($relTable), $relAlias, $condition);
    }
}