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

    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $id = $row['id'];
        $name = AttributeFieldConverter::prepareFieldName($id);
        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->fields[$name . 'Id'] = [
            'type'        => 'varchar',
            'name'        => $name,
            'attributeId' => $id,
            'column'      => 'reference_value',
            'required'    => !empty($row['is_required'])
        ];

        $entity->fields[$name . 'Name'] = [
            'type'        => 'varchar',
            'notStorable' => true
        ];

        $attributeData = @json_decode($row['data'], true)['field'] ?? null;
        $entity->set($name . 'Id', $row[$entity->fields[$name . 'Id']['column']] ?? null);

        if (!empty($attributeData['entityType'])) {
            $entity->entityDefs['fields'][$name] = [
                'attributeId' => $id,
                'type'        => 'link',
                'entity'      => $attributeData['entityType'],
                'required'    => !empty($row['is_required']),
                'label'       => $row[$this->prepareKey('name', $row)],
                'tooltip'     => !empty($row[$this->prepareKey('tooltip', $row)]),
                'tooltipText' => $row[$this->prepareKey('tooltip', $row)],
                'fullWidth'   => !empty($attributeData['fullWidth']),
            ];

            $referenceTable = Util::toUnderScore(lcfirst($attributeData['entityType']));
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

            $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
        }
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        if (!empty($attributeData['entityType'])) {
            $referenceTable = Util::toUnderScore(lcfirst($attributeData['entityType']));

            $name = AttributeFieldConverter::prepareFieldName($row['id']);

            $referenceAlias = "{$alias}{$referenceTable}";
            $qb->leftJoin($alias, $this->conn->quoteIdentifier($referenceTable), $referenceAlias, "{$referenceAlias}.id={$alias}.reference_value AND {$referenceAlias}.deleted=:false AND {$alias}.attribute_id=:{$alias}AttributeId");

            $qb->addSelect("{$referenceAlias}.id as " . $mapper->getQueryConverter()->fieldToAlias($name . 'Id'));
            $qb->addSelect("{$referenceAlias}.name as " . $mapper->getQueryConverter()->fieldToAlias($name . 'Name'));
        }
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        if(!empty($item['subQuery'])) {
            $attributeData = @json_decode($row['data'], true)['field'] ?? null;
            if(!empty($attributeData['entityType'])) {
                $this->convertSubquery($entity, $attributeData['entityType'] , $item);
            }
        }

        $item['attribute'] = 'referenceValue';

        return $item;
    }
}
