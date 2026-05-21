<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;

class V2Dot4Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-05-15 12:00:00');
    }

    public function up(): void
    {
        $this->migrateExtensibleEnumsToLinks();
        $this->migrateAttributeTypes();
        $this->createCustomExtensibleEnumEntity();
        $this->migrateExtensibleEnumTranslations();
    }

    private function createCustomExtensibleEnumEntity(): void
    {
        $dataPath = 'data/metadata';

        $definitions = [
            'entityDefs' => [
                'ExtensibleEnum' => [
                    'fields' => [
                        'name'                  => ['type' => 'varchar', 'required' => true, 'trim' => true],
                        'description'           => ['type' => 'text'],
                        'code'                  => ['type' => 'varchar', 'unique' => true],
                        'extensibleEnumOptions' => ['type' => 'linkMultiple', 'layoutDetailDisabled' => true, 'noLoad' => true],
                        'createdAt'             => ['type' => 'datetime', 'readOnly' => true],
                        'modifiedAt'            => ['type' => 'datetime', 'readOnly' => true],
                        'createdBy'             => ['type' => 'link', 'readOnly' => true, 'view' => 'views/fields/user'],
                        'modifiedBy'            => ['type' => 'link', 'readOnly' => true, 'view' => 'views/fields/user'],
                    ],
                    'links' => [
                        'extensibleEnumOptions' => [
                            'type'         => 'hasMany',
                            'foreign'      => 'extensibleEnums',
                            'entity'       => 'ExtensibleEnumOption',
                            'relationName' => 'ExtensibleEnumExtensibleEnumOption',
                        ],
                        'createdBy'  => ['type' => 'belongsTo', 'entity' => 'User'],
                        'modifiedBy' => ['type' => 'belongsTo', 'entity' => 'User'],
                    ],
                    'collection' => ['sortBy' => 'createdAt', 'asc' => false],
                    'indexes'    => [
                        'name'      => ['columns' => ['name', 'deleted']],
                        'createdAt' => ['columns' => ['createdAt', 'deleted']],
                    ],
                ],
                'ExtensibleEnumOption' => [
                    'fields' => [
                        'name'            => ['type' => 'varchar', 'isMultilang' => true],
                        'code'            => ['type' => 'varchar', 'unique' => true],
                        'extensibleEnums' => ['type' => 'linkMultiple'],
                        'color'           => ['type' => 'color'],
                        'sortOrder'       => ['type' => 'int'],
                        'createdAt'       => ['type' => 'datetime', 'readOnly' => true],
                        'modifiedAt'      => ['type' => 'datetime', 'readOnly' => true],
                        'createdBy'       => ['type' => 'link', 'readOnly' => true, 'view' => 'views/fields/user'],
                        'modifiedBy'      => ['type' => 'link', 'readOnly' => true, 'view' => 'views/fields/user'],
                    ],
                    'links' => [
                        'extensibleEnums' => [
                            'type'         => 'hasMany',
                            'foreign'      => 'extensibleEnumOptions',
                            'entity'       => 'ExtensibleEnum',
                            'relationName' => 'ExtensibleEnumExtensibleEnumOption',
                        ],
                        'createdBy'  => ['type' => 'belongsTo', 'entity' => 'User'],
                        'modifiedBy' => ['type' => 'belongsTo', 'entity' => 'User'],
                    ],
                    'collection' => ['sortBy' => 'sortOrder', 'asc' => true],
                    'indexes'    => [
                        'createdAt' => ['columns' => ['createdAt', 'deleted']],
                    ],
                ],
            ],
            'scopes' => [
                'ExtensibleEnum' => [
                    'entity'             => true,
                    'layouts'            => true,
                    'tab'                => true,
                    'acl'                => true,
                    'customizable'       => true,
                    'importable'         => true,
                    'notifications'      => true,
                    'streamDisabled'     => true,
                    'disabled'           => false,
                    'type'               => 'Base',
                    'object'             => true,
                    'hideFieldTypeFilters' => true,
                    'hasOwner'           => true,
                    'hasAssignedUser'    => true,
                    'hasTeam'            => true,
                    'matchingDisabled'   => true,
                    'valueLockDisabled'  => true,
                    'module'             => 'Custom',
                    'isCustom'           => true,
                ],
                'ExtensibleEnumOption' => [
                    'entity'             => true,
                    'layouts'            => true,
                    'tab'                => true,
                    'acl'                => true,
                    'customizable'       => true,
                    'importable'         => true,
                    'notifications'      => true,
                    'streamDisabled'     => true,
                    'disabled'           => false,
                    'type'               => 'Base',
                    'object'             => true,
                    'hideFieldTypeFilters' => true,
                    'hasOwner'           => true,
                    'hasAssignedUser'    => true,
                    'hasTeam'            => true,
                    'matchingDisabled'   => true,
                    'valueLockDisabled'  => true,
                    'module'             => 'Custom',
                    'isCustom'           => true,
                ],
            ],
            'clientDefs' => [
                'ExtensibleEnum' => [
                    'controller' => 'controllers/record',
                    'iconClass'  => 'list-plus',
                ],
                'ExtensibleEnumOption' => [
                    'controller' => 'controllers/record',
                    'iconClass'  => 'list',
                ],
            ],
        ];

        foreach ($definitions as $type => $entities) {
            $dir = "$dataPath/$type";
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            foreach ($entities as $entityName => $canonical) {
                $filePath = "$dir/$entityName.json";

                $data = $canonical;
                if (file_exists($filePath)) {
                    $existing = json_decode(file_get_contents($filePath), true) ?? [];
                    $data = array_replace_recursive($canonical, $existing);
                }

                file_put_contents(
                    $filePath,
                    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }
        }
    }

    private function migrateExtensibleEnumTranslations(): void
    {
        $file = 'data/reference-data/Translation.json';
        if (!file_exists($file)) {
            return;
        }

        $translations = json_decode(file_get_contents($file), true) ?? [];

        $defaults = [
            'ExtensibleEnum.fields.extensibleEnumOptions'                  => 'List Options',
            'ExtensibleEnum.exceptions.extensibleEnumIsUsed'               => "The List '%s' is used by the field '%s' in the Entity '%s'.",
            'ExtensibleEnumOption.fields.name'                             => 'Option Value',
            'ExtensibleEnumOption.fields.color'                            => 'Color',
            'ExtensibleEnumOption.fields.sortOrder'                        => 'Sort Order',
            'ExtensibleEnumOption.fields.extensibleEnums'                  => 'Lists',
            'ExtensibleEnumOption.exceptions.extensibleEnumOptionIsSystem' => "The List Option '%s' is required by the system.",
            'ExtensibleEnumOption.exceptions.extensibleEnumOptionIsUsed'   => "The List Option '%s' is used by the field '%s' in the Entity '%s' for the Record '%s'.",
            'Global.scopeNames.ExtensibleEnum'                             => 'List',
            'Global.scopeNames.ExtensibleEnumOption'                       => 'List Option',
            'Global.scopeNames.ExtensibleEnumExtensibleEnumOption'         => 'List (List Option)',
            'Global.scopeNamesPlural.ExtensibleEnum'                       => 'Lists',
            'Global.scopeNamesPlural.ExtensibleEnumOption'                 => 'List Options',
        ];

        $changed = false;
        foreach ($defaults as $code => $enUs) {
            if (isset($translations[$code])) {
                if (empty($translations[$code]['isCustomized'])) {
                    $translations[$code]['isCustomized'] = true;
                    $changed = true;
                }
            } else {
                $translations[$code] = [
                    'id'           => md5($code),
                    'code'         => $code,
                    'module'       => 'custom',
                    'isCustomized' => true,
                    'createdAt'    => date('Y-m-d H:i:s'),
                    'enUs'         => $enUs,
                ];
                $changed = true;
            }
        }

        if ($changed) {
            file_put_contents(
                $file,
                json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
    }

    private function migrateExtensibleEnumsToLinks(): void
    {
        $enumOptionFile = 'data/metadata/entityDefs/ExtensibleEnumOption.json';
        $enumOptionDefs = file_exists($enumOptionFile)
            ? (json_decode(file_get_contents($enumOptionFile), true) ?? [])
            : [];
        $enumOptionChanged = false;

        foreach (glob('data/metadata/entityDefs/*.json') as $file) {
            $entityName = basename($file, '.json');
            if ($entityName === 'ExtensibleEnumOption') {
                continue;
            }

            $tableName = Util::toUnderScore(lcfirst($entityName));
            $defs = json_decode(file_get_contents($file), true) ?? [];
            $changed = false;

            foreach ($defs['fields'] ?? [] as $field => $fieldDefs) {
                $col = Util::toUnderScore(lcfirst($field));
                $type = $fieldDefs['type'] ?? '';
                $enumId = $fieldDefs['extensibleEnumId'] ?? null;
                $foreignName = lcfirst($field) . ucfirst(lcfirst($entityName)) . 's' . substr(md5($entityName . $field), 0, 8);

                if ($type === 'extensibleEnum') {
                    $this->renameColumn($tableName, $col, $col . '_id');

                    $defs['fields'][$field] = $this->buildLinkDefs($fieldDefs, $enumId);
                    $defs['links'][$field] = [
                        'type'     => 'belongsTo',
                        'entity'   => 'ExtensibleEnumOption',
                        'foreign'  => $foreignName,
                        'isCustom' => true,
                    ];

                    $enumOptionDefs['fields'][$foreignName] = $this->buildReverseLinkMultipleFieldDefs();
                    $enumOptionDefs['links'][$foreignName] = [
                        'type'    => 'hasMany',
                        'foreign' => $field,
                        'entity'  => $entityName,
                    ];

                    $changed = true;
                    $enumOptionChanged = true;
                }

                if ($type === 'extensibleMultiEnum') {
                    $relationName = $entityName . ucfirst($field);

                    $this->createRelationTable($relationName, $tableName);
                    $this->migrateJsonToRelation($tableName, $col, $relationName);
                    $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " DROP COLUMN " . $this->getDbal()->quoteIdentifier($col));

                    $defs['fields'][$field] = $this->buildLinkMultipleDefs($fieldDefs, $enumId);
                    $defs['links'][$field] = [
                        'type'         => 'hasMany',
                        'entity'       => 'ExtensibleEnumOption',
                        'relationName' => $relationName,
                        'foreign'      => $foreignName,
                        'isCustom'     => true,
                    ];

                    $enumOptionDefs['fields'][$foreignName] = $this->buildReverseLinkMultipleFieldDefs();
                    $enumOptionDefs['links'][$foreignName] = [
                        'type'         => 'hasMany',
                        'foreign'      => $field,
                        'entity'       => $entityName,
                        'relationName' => $relationName,
                    ];

                    $changed = true;
                    $enumOptionChanged = true;
                }
            }

            if ($changed) {
                file_put_contents($file, json_encode($defs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        if ($enumOptionChanged) {
            file_put_contents($enumOptionFile, json_encode($enumOptionDefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private function migrateAttributeTypes(): void
    {
        try {
            $rows = $this->getPDO()
                ->query("SELECT id, type, data FROM attribute WHERE type IN ('extensibleEnum','extensibleMultiEnum') AND deleted=false")
                ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return;
        }

        foreach ($rows as $row) {
            $newType = $row['type'] === 'extensibleEnum' ? 'link' : 'linkMultiple';
            $data    = !empty($row['data']) ? (@json_decode((string)$row['data'], true) ?? []) : [];

            if (!isset($data['field'])) {
                $data['field'] = [];
            }

            $data['field']['entityType']  = 'ExtensibleEnumOption';
            $data['field']['entityField'] = 'name';
            unset($data['field']['allowedOptions']);

            $this->getDbal()->update('attribute', [
                'type' => $newType,
                'data' => json_encode($data),
            ], ['id' => $row['id']]);
        }
    }

    private function renameColumn(string $table, string $from, string $to): void
    {
        $t = $this->getDbal()->quoteIdentifier($table);
        $f = $this->getDbal()->quoteIdentifier($from);
        $n = $this->getDbal()->quoteIdentifier($to);

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE $t RENAME COLUMN $f TO $n");
        } else {
            $this->exec("ALTER TABLE $t CHANGE $f $n VARCHAR(36) DEFAULT NULL");
        }
    }

    private function createRelationTable(string $relationName, string $entityTable): void
    {
        $table = $this->getDbal()->quoteIdentifier(Util::toUnderScore(lcfirst($relationName)));

        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE IF NOT EXISTS $table (
                id VARCHAR(36) NOT NULL,
                deleted BOOLEAN NOT NULL DEFAULT FALSE,
                {$entityTable}_id VARCHAR(36),
                extensible_enum_option_id VARCHAR(36)
            )");
        } else {
            $this->exec("CREATE TABLE IF NOT EXISTS $table (
                id VARCHAR(36) NOT NULL,
                deleted TINYINT(1) NOT NULL DEFAULT 0,
                {$entityTable}_id VARCHAR(36) DEFAULT NULL,
                extensible_enum_option_id VARCHAR(36) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    private function migrateJsonToRelation(string $entityTable, string $col, string $relationName): void
    {
        $relTable = Util::toUnderScore(lcfirst($relationName));

        try {
            $stmt = $this->getPDO()->query(
                "SELECT id, $col FROM $entityTable WHERE $col IS NOT NULL AND $col != '' AND $col != '[]'"
            );
        } catch (\Throwable $e) {
            return;
        }

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $ids = json_decode($row[$col], true);
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $optionId) {
                if (empty($optionId)) {
                    continue;
                }
                try {
                    $this->getDbal()->insert($relTable, [
                        'id'                        => $this->generateId(),
                        'deleted'                   => false,
                        $entityTable . '_id'        => $row['id'],
                        'extensible_enum_option_id' => $optionId,
                    ]);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    private function generateId(): string
    {
        return IdGenerator::uuid();
    }

    private function buildReverseLinkMultipleFieldDefs(): array
    {
        return [
            'type'                  => 'linkMultiple',
            'noLoad'                => true,
            'layoutDetailDisabled'  => true,
            'massUpdateDisabled'    => true,
            'isCustom'              => true,
        ];
    }

    private function buildLinkDefs(array $orig, ?string $enumId): array
    {
        $defs = ['type' => 'link'];

        foreach (['required', 'readOnly', 'isCustom', 'audited', 'dropdown', 'inheritanceDisabled',
                  'duplicateIgnore', 'tooltip', 'default', 'conditionalProperties',
                  'modifiedExtendedDisabled', 'prohibitedEmptyValue'] as $key) {
            if (array_key_exists($key, $orig)) {
                $defs[$key] = $orig[$key];
            }
        }

        $defs['foreignName'] = 'name';

        if ($enumId) {
            $defs['extensibleEnumId'] = $enumId;
            $defs['where']            = $this->buildEnumWhere($enumId);
        }

        $defs['isCustom'] = $orig['isCustom'] ?? true;

        return $defs;
    }

    private function buildLinkMultipleDefs(array $orig, ?string $enumId): array
    {
        $defs = ['type' => 'linkMultiple'];

        foreach (['required', 'readOnly', 'isCustom', 'audited', 'dropdown', 'inheritanceDisabled',
                  'duplicateIgnore', 'tooltip', 'noLoad', 'layoutDetailDisabled', 'massUpdateDisabled',
                  'conditionalProperties', 'modifiedExtendedDisabled'] as $key) {
            if (array_key_exists($key, $orig)) {
                $defs[$key] = $orig[$key];
            }
        }

        $defs['foreignName'] = 'name';

        if ($enumId) {
            $defs['extensibleEnumId'] = $enumId;
            $defs['where']            = $this->buildEnumWhere($enumId);
        }

        $defs['isCustom'] = $orig['isCustom'] ?? true;

        return $defs;
    }

    private function buildEnumWhere(string $enumId): array
    {
        return [
            [
                'condition' => 'AND',
                'rules'     => [
                    [
                        'id'       => 'extensibleEnums',
                        'field'    => 'extensibleEnums',
                        'type'     => 'string',
                        'operator' => 'linked_with',
                        'value'    => [$enumId],
                    ],
                ],
                'valid' => true,
            ],
        ];
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
