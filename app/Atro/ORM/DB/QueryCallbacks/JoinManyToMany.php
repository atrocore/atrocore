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

namespace Atro\ORM\DB\QueryCallbacks;

use Atro\ORM\DB\Mapper;
use Atro\ORM\DB\Query\QueryConverter;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class JoinManyToMany
{
    protected IEntity $entity;
    protected string $relationName;
    protected array $keySet;
    protected QueryConverter $queryConverter;

    public function __construct(IEntity $entity, string $relationName, array $keySet, QueryConverter $queryConverter)
    {
        $this->entity = $entity;
        $this->relationName = $relationName;
        $this->keySet = $keySet;
        $this->queryConverter = $queryConverter;
    }

    public function run(QueryBuilder $qb, IEntity $entity, array $params): void
    {
        $keySet = $this->keySet;

        $relOpt = $this->entity->relations[$this->relationName];

        $key = $keySet['key'];
        $foreignKey = $keySet['foreignKey'];
        $nearKey = $keySet['nearKey'];
        $distantKey = $keySet['distantKey'];

        $relTable = $this->queryConverter->toDb($relOpt['relationName']);
        $relAlias = $this->queryConverter->getRelationAlias($entity, $relOpt['relationName']);
//        $distantTable = $mapper->toDb($relOpt['entity']);

        $condition = QueryConverter::TABLE_ALIAS . ".{$this->queryConverter->toDb($foreignKey)} = {$relAlias}.{$this->queryConverter->toDb($distantKey)}";

        $condition .= " AND {$relAlias}.{$this->queryConverter->toDb($nearKey)} = :{$key}_mm";
        $qb->setParameter("{$key}_mm", Mapper::getParameterType($entity->get($key)));
        $condition .= " AND {$relAlias}.deleted = :deleted_mm";
        $qb->setParameter("deleted_mm", Mapper::getParameterType(false));

        $conditions = $relOpt['conditions'] ?? [];
        foreach ($conditions as $f => $v) {
            $condition .= " AND {$relAlias}.{$this->queryConverter->toDb($f)} = :{$f}_mm";
            $qb->setParameter("{$f}_mm", Mapper::getParameterType($v));
        }

        $conditions = $params['additionalColumnsConditions'] ?? [];
        foreach ($conditions as $f => $v) {
            $condition .= " AND {$relAlias}.{$this->queryConverter->toDb($f)} = :{$f}_mm1";
            $qb->setParameter("{$f}_mm1", Mapper::getParameterType($v));
        }

        $qb->innerJoin(QueryConverter::TABLE_ALIAS, $relTable, $relAlias, $condition);
    }
}