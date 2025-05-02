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

use Atro\Core\AttributeFieldConverter;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class CompositeType extends AbstractFieldType
{
    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $entity->entityDefs['fields'][$name] = [
            'attributeId' => $row['id'],
            'type'        => 'composite',
            'label'       => $row[$this->prepareKey('name', $row)]
        ];

        $entity->entityDefs['fields'][$name]['childrenIds'] = $this->em->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->em->getConnection()->quoteIdentifier('attribute'))
            ->where('deleted=:false')
            ->andWhere('composite_attribute_id=:id')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $row['id'])
            ->fetchFirstColumn();

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
    }
}
