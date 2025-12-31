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
    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $entity->entityDefs['fields'][$name] = [
            'attributeId'               => $row['id'],
            'attributeValueId'          => $row['av_id'] ?? null,
            'classificationAttributeId' => $row['classification_attribute_id'] ?? null,
            'attributePanelId'          => $row['attribute_panel_id'] ?? null,
            'sortOrder'                 => $row['sort_order'] ?? null,
            'sortOrderInAttributeGroup' => $row['sort_order_in_attribute_group'] ?? null,
            'attributeGroup'            => [
                'id'        => $row['attribute_group_id'] ?? null,
                'name'      => $row['attribute_group_name'] ?? null,
                'sortOrder' => $row['attribute_group_sort_order'] ?? null,
            ],
            'channelId'                 => $row['channel_id'] ?? null,
            'channelName'               => $row['channel_name'] ?? null,
            'type'                      => 'composite',
            'label'                     => $row[$this->prepareKey('name', $row)],
            'fullWidth'                 => true,
            'notSortable'               => true,
            'conditionalProperties'     => $this->prepareConditionalProperties($row),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
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

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
    }
}
