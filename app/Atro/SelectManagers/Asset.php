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

namespace Atro\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Atro\Core\Exceptions\BadRequest;
use Espo\Core\SelectManagers\Base;
use Espo\ORM\IEntity;

class Asset extends Base
{
    protected function boolFilterOnlyPrivate(array &$result): void
    {
        $result['callbacks'][] = [$this, 'filterOnlyPrivate'];
    }

    protected function boolFilterOnlyPublic(array &$result): void
    {
        $result['callbacks'][] = [$this, 'filterOnlyPublic'];
    }

    public function filterOnlyPublic(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
        $qb->andWhere("EXISTS (SELECT e_attachment.id FROM attachment e_attachment WHERE e_attachment.id=$tableAlias.file_id AND e_attachment.private=:false AND deleted=:false)");
        $qb->setParameter('false', false, ParameterType::BOOLEAN);
    }

    public function filterOnlyPrivate(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
        $qb->andWhere("EXISTS (SELECT e_attachment.id FROM attachment e_attachment WHERE e_attachment.id=$tableAlias.file_id AND e_attachment.private=:true AND deleted=:false)");
        $qb->setParameter('true', true, ParameterType::BOOLEAN);
        $qb->setParameter('false', false, ParameterType::BOOLEAN);
    }
}
