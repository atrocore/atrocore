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
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class TextType extends AbstractFieldType
{
    protected string $type = 'text';
    protected string $column = 'text_value';

    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $id = $row['id'];
        $name = AttributeFieldConverter::prepareFieldName($id);
        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->fields[$name] = [
            'type'        => $this->type,
            'name'        => $name,
            'attributeId' => $id,
            'column'      => $this->column,
            'required'    => !empty($row['is_required'])
        ];

        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $entity->entityDefs['fields'][$name] = [
            'attributeId' => $id,
            'type'        => $this->type,
            'required'    => !empty($row['is_required']),
            'notNull'     => !empty($row['not_null']),
            'label'       => $row[$this->prepareKey('name', $row)],
            'tooltip'     => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText' => $row[$this->prepareKey('tooltip', $row)]
        ];

        if (!empty($attributeData['maxLength'])) {
            $entity->entityDefs['fields'][$name]['maxLength'] = $attributeData['maxLength'];
        }

        if (!empty($attributeData['countBytesInsteadOfCharacters'])) {
            $entity->entityDefs['fields'][$name]['countBytesInsteadOfCharacters'] = $attributeData['countBytesInsteadOfCharacters'];
        }

        $languages = [];
        if (!empty($this->config->get('isMultilangActive'))) {
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $languages[$code] = $code;
                foreach ($this->config->get('referenceData.Language', []) as $v) {
                    if ($code === $v['code']) {
                        $languages[$code] = $v['name'];
                        break;
                    }
                }
            }
        }

        if (!empty($row['is_multilang'])) {
            foreach ($languages as $language => $languageName) {
                $lName = $name . ucfirst(Util::toCamelCase(strtolower($language)));
                $entity->fields[$lName] = array_merge($entity->entityDefs['fields'][$name], [
                    'name'   => $lName,
                    'column' => $this->column . "_" . strtolower($language)
                ]);
                $entity->set($lName, $row[$entity->fields[$lName]['column']] ?? null);

                $entity->entityDefs['fields'][$lName] = array_merge($entity->entityDefs['fields'][$name], [
                    'name'        => $lName,
                    'label'       => $row[$this->prepareKey('name', $row)] . ' / ' . $languageName,
                    'tooltip'     => !empty($row[$this->prepareKey('tooltip', $row)]),
                    'tooltipText' => $row[$this->prepareKey('tooltip', $row)]
                ]);

                $attributesDefs[$lName] = $entity->entityDefs['fields'][$lName];
            }
        }

        if ($this->type === 'varchar' && isset($row['measure_id']) && empty($row['is_multilang'])) {
            $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];
            $entity->entityDefs['fields'][$name]['layoutDetailView'] = "views/fields/unit-{$this->type}";

            $entity->fields[$name . 'UnitId'] = [
                'type'        => 'varchar',
                'name'        => $name,
                'attributeId' => $id,
                'column'      => 'reference_value',
                'required'    => !empty($row['is_required'])
            ];
            $entity->fields[$name . 'UnitName'] = [
                'type'        => 'varchar',
                'notStorable' => true
            ];
            $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);

            $entity->entityDefs['fields'][$name . 'Unit'] = [
                "type"                 => "measure",
                'label'                => "{$row[$this->prepareKey('name', $row)]} " . $this->language->translate('unitPart'),
                "view"                 => "views/fields/unit-link",
                "measureId"            => $row['measure_id'],
                "entity"               => 'Unit',
                "unitIdField"          => true,
                "mainField"            => $name,
                'required'             => !empty($row['is_required']),
                'layoutDetailDisabled' => true
            ];
            $attributesDefs[$name . 'Unit'] = $entity->entityDefs['fields'][$name . 'Unit'];
        }
        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $qb->addSelect("{$alias}.{$this->column} as " . $mapper->getQueryConverter()->fieldToAlias($name));

        if (!empty($this->config->get('isMultilangActive')) && !empty($row['is_multilang'])) {
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $lName = $name . ucfirst(Util::toCamelCase(strtolower($code)));
                $qb->addSelect("{$alias}.{$this->column}_" . strtolower($code) . " as " . $mapper->getQueryConverter()->fieldToAlias($lName));
            }
        }

        if ($this->type === 'varchar' && isset($row['measure_id']) && empty($row['is_multilang'])) {
            $qb->addSelect("{$alias}.reference_value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitId"));
        }
    }
}
