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

namespace Atro\Core\AttributeFieldTypes;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

interface AttributeFieldTypeInterface
{
    public function convert(IEntity $entity, array $row, array &$attributesDefs): void;

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void;

    public function getWherePart(IEntity $entity, array $attribute, array &$item): void;
}