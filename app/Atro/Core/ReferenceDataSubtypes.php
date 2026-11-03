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

namespace Atro\Core;

/**
 * Builds the metadata of the subtypes of the reference data entities "Entity" and "EntityField": one subtype per value
 * of the "type" field. A subtype record is the supertype record with the fields that belong to its type only, so the
 * API of each type is described by its own schema. The records are stored by the supertype, the subtype has no
 * storage of its own and the ID of a subtype record is the ID of the supertype record.
 *
 * A field belongs to the types for which it is visible: the field types define their parameters in
 * "fields.<type>.params", the other fields restrict their visibility by the conditions on the "type" field. A field
 * that belongs to all types is a field of the supertype.
 */
class ReferenceDataSubtypes
{
    /**
     * The types of entities, the ones that can be created via the API are true.
     */
    public const ENTITY_TYPES = [
        'Base'          => true,
        'Hierarchy'     => true,
        'Subtype'       => true,
        'ReferenceData' => false,
        'Relation'      => false,
        'Archive'       => false,
    ];

    /**
     * The fields of an entity field that are not parameters of the field types, but belong to some of them.
     */
    public const ENTITY_FIELD_TYPE_FIELDS = [
        'optionColors'            => ['enum', 'multiEnum'],
        'translatedOptions'       => ['enum', 'multiEnum'],
        'outputType'              => ['script'],
        'preview'                 => ['script'],
        'defaultName'             => ['link', 'measure', 'file'],
        'defaultNames'            => ['linkMultiple'],
        'fileTypesNames'          => ['linkMultiple'],
        'isDuplicatableRelation'  => ['linkMultiple'],
        'modifiedExtendedEnabled' => ['linkMultiple'],
    ];

    public function prepare(array &$data): void
    {
        $fieldTypes = [];
        $paramTypes = [];
        foreach ($data['fields'] ?? [] as $type => $defs) {
            $fieldTypes[$type] = empty($defs['notCreatable']);

            foreach ($defs['params'] ?? [] as $param) {
                if (!empty($param['name'])) {
                    $paramTypes[$param['name']][] = $type;
                }
            }
        }

        $this->prepareSupertype($data, 'EntityField', $fieldTypes, $paramTypes, self::ENTITY_FIELD_TYPE_FIELDS);
        $this->prepareSupertype($data, 'Entity', self::ENTITY_TYPES, [], []);
    }

    public static function getSubtypeName(string $supertype, string $type): string
    {
        return $supertype . 'Type' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $type)));
    }

    /**
     * @param array $types      the values of the "type" field, the ones that can be created are true
     * @param array $paramTypes the types of each parameter of the field types
     * @param array $typeFields the types of the fields that are not parameters of the field types
     */
    protected function prepareSupertype(array &$data, string $supertype, array $types, array $paramTypes, array $typeFields): void
    {
        $supertypeDefs = $data['entityDefs'][$supertype] ?? [];
        if (empty($supertypeDefs['fields']) || empty($data['scopes'][$supertype])) {
            return;
        }

        $commonFields = [];
        $fieldsOfType = [];
        foreach ($supertypeDefs['fields'] as $field => $defs) {
            if (empty($defs['type']) || $field === 'type') {
                continue;
            }

            $fieldTypes = $this->getFieldTypes($field, $defs, array_keys($types), $paramTypes, $typeFields);
            if (count($fieldTypes) === count($types)) {
                $commonFields[] = $field;
                continue;
            }

            // the field is described by the schemas of the subtypes it belongs to
            $data['entityDefs'][$supertype]['fields'][$field]['openApiDisabled'] = true;

            foreach ($fieldTypes as $type) {
                $fieldsOfType[$type][] = $field;
            }
        }

        $subtypes = [];
        foreach ($types as $type => $creatable) {
            $subtype = self::getSubtypeName($supertype, (string)$type);
            if (isset($data['scopes'][$subtype])) {
                continue;
            }

            $subtypes[$type] = $subtype;

            $data['scopes'][$subtype] = array_merge($data['scopes'][$supertype], [
                'type'          => 'ReferenceData',
                'supertypeId'   => $supertype,
                'typeOption'    => $type,
                'object'        => false,
                'tab'           => false,
                'layouts'       => false,
                'customizable'  => false,
                'importable'    => false,
                'emHidden'      => true,
                'openApiHidden' => false,
            ]);

            $fields = ['type' => $this->getTypeFieldDefs($supertypeDefs['fields']['type'] ?? [], (string)$type)];
            foreach (array_merge($commonFields, $fieldsOfType[$type] ?? []) as $field) {
                $fields[$field] = $supertypeDefs['fields'][$field];
            }

            $data['entityDefs'][$subtype] = [
                'fields'     => $fields,
                'links'      => array_intersect_key($supertypeDefs['links'] ?? [], $fields),
                'collection' => $supertypeDefs['collection'] ?? [],
            ];

            $data['app']['services'][$subtype] = '\\Atro\\Core\\Templates\\Services\\ReferenceDataSubtype';

            if (!$creatable) {
                $data['clientDefs'][$subtype]['createDisabled'] = true;
            }
        }

        $data['scopes'][$supertype]['referenceDataSubtypes'] = $subtypes;
    }

    /**
     * The "type" of a subtype record is defined by the subtype.
     */
    protected function getTypeFieldDefs(array $defs, string $type): array
    {
        unset($defs['view'], $defs['conditionalProperties']);

        return array_merge($defs, [
            'type'      => 'enum',
            'options'   => [$type],
            'default'   => $type,
            'required'  => false,
            'protected' => true,
        ]);
    }

    /**
     * The types for which the field is visible. Only the conditions of the top level of the visibility conditions are
     * taken into account, the other ones do not depend on the type.
     */
    protected function getFieldTypes(string $field, array $defs, array $types, array $paramTypes, array $typeFields): array
    {
        if (isset($typeFields[$field])) {
            $types = array_intersect($types, $typeFields[$field]);
        }

        if (isset($paramTypes[$field])) {
            $types = array_intersect($types, $paramTypes[$field]);
        }

        foreach ($defs['conditionalProperties']['visible']['conditionGroup'] ?? [] as $condition) {
            if (($condition['attribute'] ?? null) !== 'type') {
                continue;
            }

            $values = (array)($condition['value'] ?? []);
            switch ($condition['type'] ?? null) {
                case 'in':
                case 'equals':
                    $types = array_intersect($types, $values);
                    break;
                case 'notIn':
                case 'notEquals':
                    $types = array_diff($types, $values);
                    break;
            }
        }

        return array_values($types);
    }
}
