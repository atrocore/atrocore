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
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\SelectManagers\Base;
use Espo\ORM\IEntity;

class Job extends Base
{
    protected function boolFilterJobManagerItems(array &$result): void
    {
        $result['callbacks'][] = [$this, 'jobManagerItems'];
    }

    public function jobManagerItems(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        if (!empty($params['aggregation'])) {
            return;
        }

        $j = $mapper->getQueryConverter()->getMainTableAlias();

        $qb->orderBy("$j.priority", 'DESC');
        $qb->addOrderBy("$j.execute_time", 'ASC');

        $qb->andWhere("$j.status=:pendingStatus");
        $qb->andWhere("$j.type IS NOT NULL");
        $qb->andWhere("$j.execute_time <= :executeTime");

        $qb->setParameter('pendingStatus', 'Pending');
        $qb->setParameter('executeTime', (new \DateTime())->format('Y-m-d H:i:s'));
    }
}
