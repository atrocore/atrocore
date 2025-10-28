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

use Atro\Core\Exceptions\BadRequest;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\SelectManagers\Base;
use Espo\ORM\IEntity;
use function React\Promise\map;

class ExtensibleEnumOption extends Base
{
    protected function boolFilterDefaultOption(array &$result): void
    {
        $data = $this->getBoolFilterParameter('defaultOption');
        if (empty($data['extensibleEnumId'])) {
            throw new BadRequest('For choosing default option, you need to select List.');
        }

        $result['callbacks'][] = function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($data) {
            $this->applyOnlyForExtensibleEnum($qb, $relEntity, $params, $mapper, $data['extensibleEnumId']);
        };
    }

    protected function boolFilterOnlyForExtensibleEnum(array &$result): void
    {
        $enumId = (string)$this->getBoolFilterParameter('onlyForExtensibleEnum');
        if (empty($enumId)) {
            return;
        }
        $result['callbacks'][] = function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($enumId) {
            $this->applyOnlyForExtensibleEnum($qb, $relEntity, $params, $mapper, $enumId);
        };
    }

    protected function boolFilterOnlyAllowedOptions(array &$result): void
    {
        $ids = $this->getBoolFilterParameter('onlyAllowedOptions');

        if (!empty($ids)) {
            $result['whereClause'][] = ['id=' => $ids];
        }
    }

    protected function boolFilterNotDisabledOptions(array &$result): void
    {
        $ids = $this->getBoolFilterParameter('notDisabledOptions');

        if (!empty($ids)) {
            $result['whereClause'][] = ['id!=' => $ids];
        }
    }

    public function applyOnlyForExtensibleEnum(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper, string $enumId): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
        if (empty($params['aggregation'])) {
            $qb->addSelect("ee_eeo.sorting");
        }
        $qb->innerJoin($tableAlias, 'extensible_enum_extensible_enum_option', "ee_eeo", "$tableAlias.id = ee_eeo.extensible_enum_option_id")
            ->innerJoin('ee_eeo', 'extensible_enum', "ee", "ee.id = ee_eeo.extensible_enum_id")
            ->andWhere("ee_eeo.deleted = :false and ee.deleted = :false and ee.id = :enumId")
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('enumId', $enumId);
    }
}
