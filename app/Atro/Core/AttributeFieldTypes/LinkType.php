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
use Atro\Core\Container;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class LinkType extends AbstractFieldType
{
    protected Connection $conn;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->conn = $container->get('connection');
    }

    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $id = $row['id'];
        $name = AttributeFieldConverter::prepareFieldName($row);
        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->fields[$name . 'Id'] = [
            'type'                     => 'varchar',
            'name'                     => $name,
            'attributeId'              => $id,
            'column'                   => 'reference_value',
            'required'                 => !empty($row['is_required']),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
        ];

        $entity->fields[$name . 'Name'] = [
            'type'        => 'varchar',
            'attributeId' => $id,
            'notStorable' => true
        ];

        if (empty($skipValueProcessing)) {
            $entity->set($name . 'Id', $row[$entity->fields[$name . 'Id']['column']] ?? null);
        }


        if (!empty($attributeData['entityType'])) {
            $entity->entityDefs['fields'][$name] = [
                'attributeId'               => $id,
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
                'type'                      => 'link',
                'entity'                    => $attributeData['entityType'],
                'dropdown'                  => $attributeData['dropdown'] ?? null,
                'required'                  => !empty($row['is_required']),
                'readOnly'                  => !empty($row['is_read_only']),
                'protected'                 => !empty($row['is_protected']),
                'label'                     => $row[$this->prepareKey('name', $row)],
                'tooltip'                   => !empty($row[$this->prepareKey('tooltip', $row)]),
                'tooltipText'               => $row[$this->prepareKey('tooltip', $row)],
                'fullWidth'                 => !empty($attributeData['fullWidth']),
                'conditionalProperties'     => $this->prepareConditionalProperties($row),
                'modifiedExtendedDisabled'  => !empty($row['modified_extended_disabled'])
            ];

            if (!empty($attributeData['dropdown'])) {
                $entity->entityDefs['fields'][$name]['view'] = 'views/fields/link-dropdown';
            }

            if (empty($skipValueProcessing)) {
                $referenceTable = Util::toUnderScore(lcfirst($attributeData['entityType']));

                if (!empty($row['reference_value'])) {
                    try {
                        $referenceItem = $this->conn->createQueryBuilder()
                            ->select('id, name')
                            ->from($referenceTable)
                            ->where('id=:id')
                            ->andWhere('deleted=:false')
                            ->setParameter('id', $row['reference_value'])
                            ->setParameter('false', false, ParameterType::BOOLEAN)
                            ->fetchAssociative();


                        $entity->set($name . 'Name', $referenceItem['name'] ?? null);
                    } catch (\Throwable $e) {
                        // ignore all
                    }
                }
            }

            $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
        }
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        if (!empty($attributeData['entityType'])) {
            $referenceTable = Util::toUnderScore(lcfirst($attributeData['entityType']));

            $name = AttributeFieldConverter::prepareFieldName($row);

            $referenceAlias = "{$alias}{$referenceTable}";
            $qb->leftJoin($alias, $this->conn->quoteIdentifier($referenceTable), $referenceAlias, "{$referenceAlias}.id={$alias}.reference_value AND {$referenceAlias}.deleted=:false AND {$alias}.attribute_id=:{$alias}AttributeId");

            $qb->addSelect("{$referenceAlias}.id as " . $mapper->getQueryConverter()->fieldToAlias($name . 'Id'));
            $qb->addSelect("{$referenceAlias}.name as " . $mapper->getQueryConverter()->fieldToAlias($name . 'Name'));

            if ($name === $params['orderBy']) {
                $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias($name . 'Name') . ' ' . $params['order']);
            }
        }
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        if (!empty($item['subQuery'])) {
            $attributeData = @json_decode($attribute['data'], true)['field'] ?? null;
            if (!empty($attributeData['entityType'])) {
                $this->convertSubquery($entity, $attributeData['entityType'], $item);
            }
        }

        $item['attribute'] = 'referenceValue';

        return $item;
    }
}
