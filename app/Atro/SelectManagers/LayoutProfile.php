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
use Espo\Core\SelectManagers\Base;
use Espo\ORM\IEntity;

class LayoutProfile extends Base
{
    protected function boolFilterWithLayouts(array &$result): void
    {
        $result['callbacks'][] = [$this, 'withLayouts'];
        $this->setDistinct(true, $result);
    }

    public function withLayouts(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $boolParams = $this->getBoolFilterParameter('withLayouts');
        $j = $mapper->getQueryConverter()->getMainTableAlias();
        $qb->leftJoin($j, 'layout', 'ly', "$j.id=ly.layout_profile_id");
        if (empty($boolParams)) {
            $qb->andWhere('ly.layout_profile_id is not null and ly.deleted=:false');
        } else {
            $qb->andWhere("ly.entity=:entity and ly.view_type=:viewType and ly.deleted=:false and " . (empty($boolParams['relatedScope']) ? "ly.related_entity is null" : "ly.related_entity = :relatedEntity"));
            $qb->setParameter('entity', $boolParams['scope'])
                ->setParameter('viewType', $boolParams['viewType'])
                ->setParameter('relatedEntity', $boolParams['relatedScope']);
        }
        $qb->setParameter('false', false, ParameterType::BOOLEAN);
    }
}
