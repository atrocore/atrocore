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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class FileType extends AbstractFieldType
{
    protected Connection $conn;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->conn = $container->get('connection');
    }

    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $entity->fields[$name . 'Id'] = [
            'type'        => 'varchar',
            'name'        => $name,
            'attributeId' => $row['id'],
            'column'      => 'reference_value',
            'required'    => !empty($row['is_required'])
        ];

        $entity->fields[$name . 'Name'] = [
            'type'        => 'varchar',
            'notStorable' => true
        ];

        $entity->fields[$name . 'PathsData'] = [
            'type'        => 'jsonObject',
            'notStorable' => true
        ];

        $entity->set($name . 'Id', $row[$entity->fields[$name . 'Id']['column']] ?? null);
        $entity->set($name . 'Name', $row['file_name'] ?? null);

        $attributeData = @json_decode($row['data'], true)['field'] ?? null;
        $attributesDefs[$name] = $entity->entityDefs['fields'][$name] = [
            'attributeId'               => $row['id'],
            'classificationAttributeId' => $row['classification_attribute_id'] ?? null,
            'channelId'                 => $row['channel_id'] ?? null,
            'type'                      => 'file',
            'required'                  => !empty($row['is_required']),
            'label'                     => $row[$this->prepareKey('name', $row)],
            'tooltip'                   => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'               => $row[$this->prepareKey('tooltip', $row)],
            'fullWidth'                 => !empty($attributeData['fullWidth']),
        ];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $fileAlias = "{$alias}file";
        $qb->leftJoin($alias, $this->conn->quoteIdentifier('file'), $fileAlias, "{$fileAlias}.id={$alias}.reference_value AND {$fileAlias}.deleted=:false AND {$alias}.attribute_id=:{$alias}AttributeId");

        $qb->addSelect("{$fileAlias}.id as " . $mapper->getQueryConverter()->fieldToAlias($name . 'Id'));
        $qb->addSelect("{$fileAlias}.name as " . $mapper->getQueryConverter()->fieldToAlias($name . 'Name'));
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        if(!empty($item['subQuery'])) {
            $this->convertSubquery($entity, 'File', $item);
        }

        $item['attribute'] = 'referenceValue';

        return $item;
    }
}
