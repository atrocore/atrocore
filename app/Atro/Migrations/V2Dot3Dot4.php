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
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;

class V2Dot3Dot4 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-05-27 18:00:00');
    }

    public function up(): void
    {
        foreach (['equal' => 'fieldEqual', 'similar' => 'fieldSimilar', 'contains' => 'fieldContains'] as $old => $new) {
            $this->getDbal()->createQueryBuilder()
                ->update('matching_rule')
                ->set('type', ':new')
                ->where('type = :old')
                ->setParameter('new', $new)
                ->setParameter('old', $old)
                ->executeQuery();
        }

        $this->exec("ALTER TABLE matching_rule ADD attribute_id VARCHAR(36) DEFAULT NULL");

        $this->migrateOptionsToMetadata();

        $this->createPrefixTable();
        $this->addPrefixEnabledToAttribute();
        $this->addPrefixValueToAttributeValue();
        $this->renameUnitFieldsInLayouts();

        $this->migrateExtensibleEnumsToLinks();
        $this->migrateAttributeTypes();
        $this->createCustomExtensibleEnumEntity();
        $this->seedExtensibleEnumLayouts();
        $this->migrateExtensibleEnumTranslations();
        $this->migrateActions();

        $this->migrateNotes();
        $this->migrateNotes2();

        $this->migrateCaDefaults();
    }

    private function migrateCaDefaults(): void
    {
        $dbal = $this->getDbal();

        $offset = 0;
        $limit = 5000;
        while (true) {
            $attributes = $dbal->createQueryBuilder()
                ->select('ca.*, a.type')
                ->from('classification_attribute', 'ca')
                ->innerJoin('ca', $dbal->quoteIdentifier('attribute'), 'a', 'a.id=ca.attribute_id AND a.deleted=:false')
                ->where('ca.deleted=:false')
                ->andWhere('ca.data IS NOT NULL')
                ->andWhere('a.type IN (:types)')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('types', ['link', 'linkMultiple'], $dbal::PARAM_STR_ARRAY)
                ->fetchAllAssociative();

            if (empty($attributes)) {
                break;
            }

            $offset = $offset + $limit;

            foreach ($attributes as $attribute) {
                $data = json_decode($attribute['data'], true);
                if (isset($data['default']['value'])) {
                    if ($attribute['type'] === 'link') {
                        $data['default']['valueId'] = $data['default']['value'];
                        unset($data['default']['value']);
                    } elseif ($attribute['type'] === 'linkMultiple') {
                        $data['default']['valueIds'] = $data['default']['value'];
                        unset($data['default']['value']);
                    } else {
                        continue;
                    }

                    $dbal->createQueryBuilder()
                        ->update('classification_attribute')
                        ->set('data', ':data')
                        ->where('id=:id')
                        ->setParameter('data', json_encode($data))
                        ->setParameter('id', $attribute['id'])
                        ->executeQuery();
                }
            }
        }
    }

    private function migrateNotes2(): void
    {
        $dbal = $this->getDbal();

        $offset = 0;
        $limit = 2000;
        while (true) {
            $notes = $dbal->createQueryBuilder()
                ->select('id, data')
                ->from($dbal->quoteIdentifier('note'))
                ->where('deleted = :false')
                ->andWhere('type = :type')
                ->andWhere('data LIKE :extensibleEnum OR data LIKE :extensibleMultiEnum')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('type', 'Update')
                ->setParameter('extensibleEnum', '%"fieldType":"extensibleEnum"%')
                ->setParameter('extensibleMultiEnum', '%"fieldType":"extensibleMultiEnum"%')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            $offset = $offset + $limit;

            if (empty($notes)) {
                break;
            }

            foreach ($notes as $note) {
                $data = @json_decode($note['data'] ?? '{}', true);
                if (empty($data['attributeData'])) {
                    continue;
                }

                $updated = false;
                foreach ($data['attributeData'] as $fieldName => $defs) {
                    if (empty($defs['fieldType'])) {
                        continue;
                    }
                    $data['attributeData'][$fieldName]['fieldType'] = $defs['fieldType'] === 'extensibleEnum' ? 'link' : 'linkMultiple';
                    $updated = true;
                }

                if ($updated) {
                    $dbal->createQueryBuilder()
                        ->update('note')
                        ->set('data', ':data')
                        ->where('id = :id')
                        ->setParameter('id', $note['id'])
                        ->setParameter('data', json_encode($data))
                        ->executeQuery();
                }
            }
        }
    }

    private function migrateNotes(): void
    {
        $dbal = $this->getDbal();

        $offset = 0;
        $limit = 2000;
        while (true) {
            $notes = $dbal->createQueryBuilder()
                ->select('id, data')
                ->from($dbal->quoteIdentifier('note'))
                ->where('deleted = :false')
                ->andWhere('type = :type')
                ->andWhere('data LIKE :optionsData OR data LIKE :optionData')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('type', 'Update')
                ->setParameter('optionsData', '%OptionsData"%')
                ->setParameter('optionData', '%OptionData"%')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            $offset = $offset + $limit;

            if (empty($notes)) {
                break;
            }

            foreach ($notes as $note) {
                $data = @json_decode($note['data'] ?? '{}', true);
                if (empty($data['attributes']['was']) || empty($data['attributes']['became'])) {
                    continue;
                }

                $updated = false;

                foreach (['was', 'became'] as $type) {
                    foreach ($data['attributes'][$type] as $key => $value) {
                        if (isset($data['attributes']['was'][$key . 'Name']) || isset($data['attributes']['became'][$key . 'Name'])) {
                            $data['attributes'][$type][$key . 'Id'] = $data['attributes'][$type][$key];
                            unset($data['attributes'][$type][$key]);
                            $updated = true;
                        } elseif (isset($data['attributes']['was'][$key . 'Names']) || isset($data['attributes']['became'][$key . 'Names'])) {
                            $data['attributes'][$type][$key . 'Ids'] = $data['attributes'][$type][$key];
                            unset($data['attributes'][$type][$key]);
                            $updated = true;
                        }
                    }
                }

                if ($updated) {
                    $dbal->createQueryBuilder()
                        ->update('note')
                        ->set('data', ':data')
                        ->where('id = :id')
                        ->setParameter('id', $note['id'])
                        ->setParameter('data', json_encode($data))
                        ->executeQuery();
                }
            }
        }
    }

    private function migrateActions(): void
    {
        $dbal = $this->getDbal();

        $actions = $dbal->createQueryBuilder()
            ->select('*')
            ->from($dbal->quoteIdentifier('action'))
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($actions as $action) {
            $data = @json_decode($action['data'] ?? '{}', true);
            if (empty($data['fieldData'])){
                continue;
            }

            $updated = false;

            foreach ($data['fieldData'] as $key => $value) {
                if (str_ends_with($key, 'Name')) {
                    $base = substr($key, 0, -strlen('Name'));
                    if (isset($data['fieldData'][$base])) {
                        $data['fieldData'][$base . 'Id'] = $data['fieldData'][$base];
                        unset($data['fieldData'][$base]);
                        $updated = true;
                    }
                } elseif (str_ends_with($key, 'Names')) {
                    $base = substr($key, 0, -strlen('Names'));
                    if (isset($data['fieldData'][$base])) {
                        $data['fieldData'][$base . 'Ids'] = $data['fieldData'][$base];
                        unset($data['fieldData'][$base]);
                        $updated = true;
                    }
                }
            }

            if ($updated) {
                $dbal->createQueryBuilder()
                    ->update($dbal->quoteIdentifier('action'))
                    ->set('data', ':data')
                    ->where('id = :id')
                    ->setParameter('id', $action['id'])
                    ->setParameter('data', json_encode($data))
                    ->executeQuery();
            }
        }
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

    private function seedExtensibleEnumLayouts(): void
    {
        $profileId = $this->getDbal()->createQueryBuilder()
            ->select('id')
            ->from('layout_profile')
            ->where('is_default = :true')
            ->andWhere('deleted = :false')
            ->setParameter('true', true, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->fetchOne();

        if (!$profileId) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $layouts = [
            ['entity' => 'ExtensibleEnum', 'viewType' => 'list',
             'items'  => [
                 ['name' => 'name', 'link' => true, 'sortOrder' => 0],
                 ['name' => 'code', 'link' => false, 'sortOrder' => 1],
             ]],
            ['entity'   => 'ExtensibleEnum', 'viewType' => 'detail',
             'sections' => [[
                                'name'     => 'Details', 'style' => 'default', 'sortOrder' => 0,
                                'rowItems' => [
                                    ['name' => 'name', 'rowIndex' => 0, 'columnIndex' => 0, 'fullWidth' => false],
                                    ['name' => 'code', 'rowIndex' => 0, 'columnIndex' => 1, 'fullWidth' => false],
                                    ['name' => 'description', 'rowIndex' => 1, 'columnIndex' => 0, 'fullWidth' => true],
                                ],
                            ]]],
            ['entity'    => 'ExtensibleEnum', 'viewType' => 'relationships',
             'relations' => [['name' => 'extensibleEnumOptions', 'sortOrder' => 0]]],
            ['entity' => 'ExtensibleEnumOption', 'viewType' => 'list',
             'items'  => [
                 ['name' => 'color', 'link' => false, 'width' => 8.0, 'sortOrder' => 0],
                 ['name' => 'name', 'link' => true, 'sortOrder' => 1],
                 ['name' => 'code', 'link' => false, 'sortOrder' => 2],
                 ['name' => 'extensibleEnums', 'link' => false, 'sortOrder' => 3],
             ]],
            ['entity'   => 'ExtensibleEnumOption', 'viewType' => 'detail',
             'sections' => [[
                                'name'     => 'Details', 'style' => 'default', 'sortOrder' => 0,
                                'rowItems' => [
                                    ['name' => 'name', 'rowIndex' => 0, 'columnIndex' => 0, 'fullWidth' => false],
                                    ['name' => 'color', 'rowIndex' => 0, 'columnIndex' => 1, 'fullWidth' => false],
                                    ['name' => 'code', 'rowIndex' => 1, 'columnIndex' => 0, 'fullWidth' => false],
                                    ['name' => 'sortOrder', 'rowIndex' => 2, 'columnIndex' => 0, 'fullWidth' => false],
                                    ['name' => 'extensibleEnums', 'rowIndex' => 2, 'columnIndex' => 1, 'fullWidth' => false],
                                ],
                            ]]],
        ];

        foreach ($layouts as $def) {
            $entity        = $def['entity'];
            $viewType      = $def['viewType'];
            $relatedEntity = $def['relatedEntity'] ?? '';
            $relatedLink   = $def['relatedLink'] ?? '';

            $hash = md5('atrocore_salt' . implode("\n", [$profileId, $entity, $relatedEntity, $relatedLink, $viewType]));

            $existing = $this->getDbal()->createQueryBuilder()
                ->select('id')
                ->from('layout')
                ->where('hash = :hash')
                ->andWhere('deleted = :false')
                ->setParameter('hash', $hash)
                ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->fetchOne();

            if ($existing) {
                continue;
            }

            $layoutId = IdGenerator::uuid();

            $qb = $this->getDbal()->createQueryBuilder()
                ->insert('layout')
                ->values([
                    'id'                => ':id',
                    'entity'            => ':entity',
                    'view_type'         => ':viewType',
                    'layout_profile_id' => ':profileId',
                    'hash'              => ':hash',
                    'deleted'           => ':false',
                    'created_at'        => ':now',
                ])
                ->setParameter('id', $layoutId)
                ->setParameter('entity', $entity)
                ->setParameter('viewType', $viewType)
                ->setParameter('profileId', $profileId)
                ->setParameter('hash', $hash)
                ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->setParameter('now', $now);

            if ($relatedEntity) {
                $qb->setValue('related_entity', ':relatedEntity')
                    ->setParameter('relatedEntity', $relatedEntity);
            }
            if ($relatedLink) {
                $qb->setValue('related_link', ':relatedLink')
                    ->setParameter('relatedLink', $relatedLink);
            }

            $qb->executeStatement();

            foreach ($def['items'] ?? [] as $item) {
                $this->getDbal()->createQueryBuilder()
                    ->insert('layout_list_item')
                    ->values([
                        'id'         => ':id',
                        'layout_id'  => ':layoutId',
                        'name'       => ':name',
                        'sort_order' => ':sortOrder',
                        'link'       => ':link',
                        'width'      => ':width',
                        'deleted'    => ':false',
                        'created_at' => ':now',
                    ])
                    ->setParameter('id', IdGenerator::uuid())
                    ->setParameter('layoutId', $layoutId)
                    ->setParameter('name', $item['name'])
                    ->setParameter('sortOrder', $item['sortOrder'], \Doctrine\DBAL\ParameterType::INTEGER)
                    ->setParameter('link', !empty($item['link']), \Doctrine\DBAL\ParameterType::BOOLEAN)
                    ->setParameter('width', $item['width'] ?? null)
                    ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                    ->setParameter('now', $now)
                    ->executeStatement();
            }

            foreach ($def['sections'] ?? [] as $sec) {
                $sectionId = IdGenerator::uuid();

                $this->getDbal()->createQueryBuilder()
                    ->insert('layout_section')
                    ->values([
                        'id'         => ':id',
                        'layout_id'  => ':layoutId',
                        'name'       => ':name',
                        'style'      => ':style',
                        'sort_order' => ':sortOrder',
                        'deleted'    => ':false',
                        'created_at' => ':now',
                    ])
                    ->setParameter('id', $sectionId)
                    ->setParameter('layoutId', $layoutId)
                    ->setParameter('name', $sec['name'])
                    ->setParameter('style', $sec['style'] ?? 'default')
                    ->setParameter('sortOrder', $sec['sortOrder'], \Doctrine\DBAL\ParameterType::INTEGER)
                    ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                    ->setParameter('now', $now)
                    ->executeStatement();

                foreach ($sec['rowItems'] as $ri) {
                    $this->getDbal()->createQueryBuilder()
                        ->insert('layout_row_item')
                        ->values([
                            'id'           => ':id',
                            'section_id'   => ':sectionId',
                            'name'         => ':name',
                            'row_index'    => ':rowIndex',
                            'column_index' => ':columnIndex',
                            'full_width'   => ':fullWidth',
                            'deleted'      => ':false',
                            'created_at'   => ':now',
                        ])
                        ->setParameter('id', IdGenerator::uuid())
                        ->setParameter('sectionId', $sectionId)
                        ->setParameter('name', $ri['name'])
                        ->setParameter('rowIndex', $ri['rowIndex'], \Doctrine\DBAL\ParameterType::INTEGER)
                        ->setParameter('columnIndex', $ri['columnIndex'], \Doctrine\DBAL\ParameterType::INTEGER)
                        ->setParameter('fullWidth', $ri['fullWidth'], \Doctrine\DBAL\ParameterType::BOOLEAN)
                        ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                        ->setParameter('now', $now)
                        ->executeStatement();
                }
            }

            foreach ($def['relations'] ?? [] as $rel) {
                $this->getDbal()->createQueryBuilder()
                    ->insert('layout_relationship_item')
                    ->values([
                        'id'         => ':id',
                        'layout_id'  => ':layoutId',
                        'name'       => ':name',
                        'sort_order' => ':sortOrder',
                        'deleted'    => ':false',
                        'created_at' => ':now',
                    ])
                    ->setParameter('id', IdGenerator::uuid())
                    ->setParameter('layoutId', $layoutId)
                    ->setParameter('name', $rel['name'])
                    ->setParameter('sortOrder', $rel['sortOrder'], \Doctrine\DBAL\ParameterType::INTEGER)
                    ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                    ->setParameter('now', $now)
                    ->executeStatement();
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
            $renameMap = [];

            foreach ($defs['fields'] ?? [] as $field => $fieldDefs) {
                $col = Util::toUnderScore(lcfirst($field));
                $type = $fieldDefs['type'] ?? '';
                $enumId = $fieldDefs['extensibleEnumId'] ?? null;
                $foreignName = lcfirst($field) . ucfirst(lcfirst($entityName)) . 's' . substr(md5($entityName . $field), 0, 8);

                if ($type === 'extensibleEnum') {
                    $renameMap[$field] = $field . 'Id';

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
                    $renameMap[$field] = $field . 'Ids';

                    $relationName = $entityName . ucfirst($field);

                    $this->createRelationTable($relationName, $tableName);
                    $this->migrateJsonToRelation($tableName, $col, $relationName);
//                    $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " DROP COLUMN " . $this->getDbal()->quoteIdentifier($col));

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

            if (!empty($renameMap)) {
                foreach ($defs['fields'] as $fName => &$fDefs) {
                    if (!isset($fDefs['conditionalProperties'])) {
                        continue;
                    }
                    $updated = $this->applyConditionalPropertiesRename($fDefs['conditionalProperties'], $renameMap);
                    if ($updated !== $fDefs['conditionalProperties']) {
                        $fDefs['conditionalProperties'] = $updated;
                        $changed = true;
                    }
                }
                unset($fDefs);
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
                ->query("SELECT id, type, data, extensible_enum_id FROM attribute WHERE type IN ('extensibleEnum','extensibleMultiEnum') AND deleted=false")
                ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return;
        }

        foreach ($rows as $row) {
            $newType = $row['type'] === 'extensibleEnum' ? 'link' : 'linkMultiple';
            $data = !empty($row['data']) ? (@json_decode((string)$row['data'], true) ?? []) : [];

            if (!isset($data['field'])) {
                $data['field'] = [];
            }

            if (isset($data['field']['allowedOptions'])) {
                unset($data['field']['allowedOptions']);
            }

            $data['field']['entityType'] = 'ExtensibleEnumOption';
            $data['field']['entityField'] = 'name';

            $data['whereScope'] = 'ExtensibleEnumOption';
            $data["where"] = [
                [
                    "condition" => "AND",
                    "rules"     => [
                        [
                            "id"       => "extensibleEnums",
                            "field"    => "extensibleEnums",
                            "type"     => "string",
                            "operator" => "linked_with",
                            "value"    => [$row['extensible_enum_id']],
                        ]
                    ],
                    "valid"     => true
                ]
            ];

            $this->getDbal()->update('attribute', ['type' => $newType, 'data' => json_encode($data)], ['id' => $row['id']]);
        }
    }

    private function applyConditionalPropertiesRename(array $conditionalProperties, array $renameMap): array
    {
        foreach ($conditionalProperties as &$propDef) {
            if (isset($propDef['conditionGroup'])) {
                // Standard form: { conditionGroup: [...] }
                foreach ($propDef['conditionGroup'] as &$condition) {
                    if (isset($condition['attribute']) && array_key_exists($condition['attribute'], $renameMap)) {
                        $condition['attribute'] = $renameMap[$condition['attribute']];
                    }
                }
                unset($condition);
            } else {
                // Array form (e.g. disableOptions): [{ options: [...], conditionGroup: [...] }, ...]
                foreach ($propDef as &$entry) {
                    foreach ($entry['conditionGroup'] ?? [] as &$condition) {
                        if (isset($condition['attribute']) && array_key_exists($condition['attribute'], $renameMap)) {
                            $condition['attribute'] = $renameMap[$condition['attribute']];
                        }
                    }
                    unset($condition);
                }
                unset($entry);
            }
        }
        unset($propDef);

        return $conditionalProperties;
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
        $table = Util::toUnderScore(lcfirst($relationName));
        $indexName = strtoupper($table);
        $upperEntityTable = strtoupper($entityTable);

        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE $table (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, extensible_enum_option_id VARCHAR(36) DEFAULT NULL, {$entityTable}_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_{$indexName}_UNIQUE_RELATION ON $table (deleted, extensible_enum_option_id, {$entityTable}_id)");
            $this->exec("CREATE INDEX IDX_{$indexName}_CREATED_BY_ID ON $table (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_{$indexName}_MODIFIED_BY_ID ON $table (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_{$indexName}_EXTENSIBLE_ENUM_OPTION_ID ON $table (extensible_enum_option_id, deleted)");
            $this->exec("CREATE INDEX IDX_{$indexName}_{$upperEntityTable}_ID ON $table ({$entityTable}_id, deleted)");
            $this->exec("CREATE INDEX IDX_{$indexName}_CREATED_AT ON $table (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_{$indexName}_MODIFIED_AT ON $table (modified_at, deleted)");
        } else {
            $this->exec("CREATE TABLE $table (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, extensible_enum_option_id VARCHAR(36) DEFAULT NULL, {$entityTable}_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_{$indexName}_UNIQUE_RELATION (deleted, extensible_enum_option_id, {$entityTable}_id), INDEX IDX_{$indexName}_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_{$indexName}_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_{$indexName}_EXTENSIBLE_ENUM_OPTION_ID (extensible_enum_option_id, deleted), INDEX IDX_{$indexName}_{$upperEntityTable}_ID ({$entityTable}_id, deleted), INDEX IDX_{$indexName}_CREATED_AT (created_at, deleted), INDEX IDX_{$indexName}_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
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

    // extensibleEnumId => [systemOptions, [Entity => fieldName]]
    private array $map = [
        // atrocore
        'gender'                  => [['Male', 'Female', 'Neutral'],                                                                                                  ['User' => 'gender', 'Contact' => 'gender', 'Prospect' => 'gender']],
        'role'                    => [['supplier', 'customer'],                                                                                                        ['Account' => 'role', 'Prospect' => 'role']],
        'addressType'             => [['billing', 'delivery'],                                                                                                        ['Address' => 'type']],
        'product_group_item_type' => [['physical_goods', 'services', 'digital_products', 'legal_rights'],                                                             ['ProductGroup' => 'itemType']],
        'team_position'           => [[],                                                                                                                             ['TeamUser' => 'role']],
        'update_type'             => [['basic', 'script'],                                                                                                            ['Action' => 'updateType']],
        'content_items'           => [['highlight', 'top_features_list', 'story'],                                                                                    ['ContentItem' => 'type']],
        'listing_status'          => [['listing_draft', 'listing_prepared', 'buyable', 'discoverable', 'listing_error'],                                             ['Listing' => 'status']],
        'pdfTemplateType'         => [['pdfTemplateHtml', 'pdfTemplateODT', 'pdfTemplateCatalog'],                                                                    ['PdfFeed' => 'type']],
        'budget_item_status'      => [[],                                                                                                                             ['BudgetItem' => 'status']],
        // sales
        'saleStatus'              => [['new', 'approved', 'in_progress', 'fulfilled', 'provided', 'partly_invoiced', 'invoiced', 'cancelled'],                        ['Sale' => 'status', 'Settings' => 'consideredSaleStatuses']],
        'billingStatus'           => [['openBillingStatus', 'inProgressBS', 'cancelledBS', 'partlyBilledBS', 'billedBS'],                                             ['Sale' => 'billingStatus']],
        'shippingStatus'          => [['openShippingStatus', 'cancelledShippingStatus', 'returnedShippingStatus', 'shippedShippingStatus'],                           ['Sale' => 'shippingStatus']],
        'shippingMethod'          => [[],                                                                                                                             ['Sale' => 'shippingMethod']],
        'documentType'            => [['documentTypeSection', 'documentTypeGroup', 'documentTypeItem', 'documentTypeNote', 'documentTypeSubtotal'],                   ['SaleItem' => 'type', 'QuotationItem' => 'type', 'SubscriptionItem' => 'type', 'InvoiceItem' => 'type', 'DebitNoteItem' => 'type', 'CreditNoteItem' => 'type']],
        'saleItemStatus'          => [['newSIS', 'approvedSIS', 'inProgressSIS', 'fulfilledSIS', 'invoicedSIS', 'cancelledSIS'],                                      ['SaleItem' => 'status']],
        'saleReturnStatus'        => [['newSaleReturnStatus', 'dispatchedSRS', 'returnedSRS'],                                                                        ['SaleReturn' => 'status']],
        'quotationStatus'         => [['newQuotationStatus', 'approvedQuotationStatus', 'negotiatingQS', 'acceptedQS', 'rejectedQS', 'cancelledQuotationStatus'],     ['Quotation' => 'status']],
        'prospectStatus'          => [['newPS', 'contactedPS', 'engagedPS', 'qualifiedPS', 'processingPS', 'quotedPS', 'followUpPS', 'wonPS', 'lostPS', 'disqualifiedPS'], ['Prospect' => 'status']],
        'confirmationStatus'      => [['newCS', 'sentCS'],                                                                                                            ['OrderConfirmation' => 'status']],
        'recurringPeriod'         => [['days', 'weeks', 'months', 'years'],                                                                                           ['Subscription' => 'recurringPeriod', 'RecurringPrice' => 'recurringPeriod']],
        'paymentSchedule'         => [['advancedPSchedule', 'deferredPSchedule'],                                                                                     ['Subscription' => 'paymentSchedule']],
        'subscriptionStatus'      => [['newSubscriptionStatus', 'activeSS', 'cancelledSS', 'pausedSS'],                                                               ['Subscription' => 'status']],
        // accounting
        'invoiceType'             => [['standardInvoiceType', 'correctionInvoiceType', 'proformaInvoiceType'],                                                         ['Invoice' => 'type']],
        'debitNoteType'           => [['standardDebitNoteType', 'correctionDNT', 'priceIncreaseDNT', 'commissionDNT'],                                                 ['DebitNote' => 'type']],
        'creditNoteType'          => [['standardCNT', 'priceReductionCNT', 'correctionCNT', 'commissionCNT'],                                                          ['CreditNote' => 'type']],
        'invoiceStatus'           => [['draftInvoiceStatus', 'readyInvoiceStatus', 'sentInvoiceStatus', 'paidInvoiceStatus', 'canceledInvoiceStatus', 'unpaidInvoiceStatus'], ['Invoice' => 'status', 'DebitNote' => 'status', 'CreditNote' => 'status']],
        // inventory
        'locationType'            => [['warehouseID', 'areaId', 'positionId'],                                                                                        ['Location' => 'type']],
        'deliveryStatus'          => [['newDeliveryStatus', 'dispatchedDeliveryStatus', 'shippedDeliveryStatus'],                                                      ['Delivery' => 'status']],
        // activities
        'task_status'             => [['task_status_new', 'task_status_in_progress', 'task_status_done', 'task_status_cancelled'],                                     ['Task' => 'status']],
        'task_priority'           => [['task_priority_normal', 'task_priority_low', 'task_priority_high'],                                                             ['Task' => 'priority']],
        // components
        'componentStatus'         => [['component_draft', 'component_draft_warning', 'component_prepared', 'component_accepted', 'component_rejected', 'component_approved', 'component_error'], ['Component' => 'status']],
        // amazon-adapter
        'componentChannelStatus'  => [['component_draft', 'component_accepted', 'component_rejected', 'component_approved'],                                          ['ComponentChannel' => 'status']],
    ];

    private function migrateOptionsToMetadata(): void
    {
        $additionalLocales = $this->getAdditionalLocales();
        $translations      = $this->loadTranslations();

        foreach ($this->map as $enumId => [$systemOptions, $entityFields]) {
            if (empty($entityFields)) {
                continue;
            }

            foreach ($entityFields as $entity => $field) {
                $tableName = Util::toUnderScore(lcfirst($entity));
                $columnName = Util::toUnderScore(lcfirst($field));

                if ($this->isPgSQL()) {
                    $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " ALTER $columnName TYPE VARCHAR(255)");
                } else {
                    $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " CHANGE $columnName $columnName VARCHAR(255) DEFAULT NULL");
                }
            }

            $allOptions    = $this->getEnumOptionsWithNames($enumId, $additionalLocales);
            $customOptions = array_filter($allOptions, fn($row) => !in_array($row['id'], $systemOptions));

            if (empty($customOptions)) {
                continue;
            }

            // Write option IDs to data/metadata/entityDefs
            $ids = array_column(array_values($customOptions), 'id');
            foreach ($entityFields as $entity => $field) {
                $file = 'data/metadata/entityDefs/' . $entity . '.json';
                $data = file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
                $data['fields'][$field]['options'] = array_merge(['__APPEND__'], $ids);
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Write translations for each custom option into Translation.json
            foreach ($customOptions as $row) {
                $optionId = $row['id'];
                foreach ($entityFields as $entity => $field) {
                    $code = "$entity.options.$field.$optionId";
                    if (!isset($translations[$code])) {
                        $translations[$code] = [
                            'id'          => md5($code),
                            'code'        => $code,
                            'module'      => 'custom',
                            'isCustomized' => true,
                            'createdAt'   => date('Y-m-d H:i:s'),
                        ];
                    }
                    // Main name → enUs
                    $translations[$code]['enUs'] = $row['name'];
                    // Additional locales: name_de_de → deDe
                    foreach ($additionalLocales as $locale) {
                        $dbCol   = 'name_' . strtolower($locale);           // name_de_de
                        $jsonKey = $this->localeToCamel($locale);            // deDe
                        if (!empty($row[$dbCol])) {
                            $translations[$code][$jsonKey] = $row[$dbCol];
                        }
                    }
                }
            }
        }

        $this->saveTranslations($translations);
    }

    private function getAdditionalLocales(): array
    {
        $file = 'data/reference-data/Language.json';
        if (!file_exists($file)) {
            return [];
        }

        $locales = [];
        foreach (json_decode(file_get_contents($file), true) ?? [] as $row) {
            if (($row['role'] ?? '') === 'additional' && !empty($row['code'])) {
                $locales[] = $row['code'];
            }
        }

        return $locales;
    }

    private function loadTranslations(): array
    {
        $file = 'data/reference-data/Translation.json';

        return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
    }

    private function saveTranslations(array $translations): void
    {
        file_put_contents(
            'data/reference-data/Translation.json',
            json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function localeToCamel(string $locale): string
    {
        // 'de_DE' → 'deDe',  'uk_UA' → 'ukUa'
        $parts = explode('_', strtolower($locale));

        return $parts[0] . ucfirst($parts[1] ?? '');
    }

    private function getEnumOptionsWithNames(string $enumId, array $additionalLocales): array
    {
        try {
            $langCols = implode(', ', array_map(
                fn($l) => 'eeo.name_' . strtolower($l),
                $additionalLocales
            ));
            $select = 'eeo.id, eeo.name' . ($langCols ? ", $langCols" : '');

            $stmt = $this->getPDO()->prepare("
                SELECT $select
                FROM extensible_enum_option eeo
                INNER JOIN extensible_enum_extensible_enum_option eeeeo
                    ON eeeeo.extensible_enum_option_id = eeo.id
                WHERE eeeeo.extensible_enum_id = :enumId
                  AND eeo.deleted = false
                  AND eeeeo.deleted = false
                ORDER BY eeeeo.sorting ASC, eeo.id ASC
            ");
            $stmt->execute([':enumId' => $enumId]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function createPrefixTable(): void
    {
        $fromSchema = $this->getSchema();
        $toSchema   = clone $fromSchema;

        if ($toSchema->hasTable('prefix')) {
            return;
        }

        $table = $toSchema->createTable('prefix');
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false, 'default' => null]);
        $table->addColumn('value', 'string', ['length' => 255, 'notnull' => false, 'default' => null]);
        $table->addColumn('deleted', 'boolean', ['notnull' => false, 'default' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false, 'default' => null]);
        $table->addColumn('modified_at', 'datetime', ['notnull' => false, 'default' => null]);
        $table->addColumn('created_by_id', 'string', ['length' => 36, 'notnull' => false, 'default' => null]);
        $table->addColumn('modified_by_id', 'string', ['length' => 36, 'notnull' => false, 'default' => null]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['name', 'deleted'], 'IDX_PREFIX_NAME');
        $table->addIndex(['created_by_id', 'deleted'], 'IDX_PREFIX_CREATED_BY_ID');
        $table->addIndex(['modified_by_id', 'deleted'], 'IDX_PREFIX_MODIFIED_BY_ID');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    private function addPrefixEnabledToAttribute(): void
    {
        $fromSchema = $this->getSchema();
        $toSchema   = clone $fromSchema;

        if (!$toSchema->hasTable('attribute')) {
            return;
        }

        $table = $toSchema->getTable('attribute');

        if (!$table->hasColumn('prefix_enabled')) {
            $table->addColumn('prefix_enabled', 'boolean', ['notnull' => true, 'default' => false]);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    private function addPrefixValueToAttributeValue(): void
    {
        $fromSchema = $this->getSchema();
        $toSchema   = clone $fromSchema;

        foreach ($toSchema->getTables() as $table) {
            if (!str_ends_with($table->getName(), '_attribute_value')) {
                continue;
            }

            if (!$table->hasColumn('prefix_value')) {
                $table->addColumn('prefix_value', 'string', ['length' => 36, 'notnull' => false, 'default' => null]);
                $table->addIndex(['prefix_value', 'deleted'], Converter::generateIndexName($table->getName(), 'prefixValue'));
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    private function renameUnitFieldsInLayouts(): void
    {
        // layout_list_item has attribute_id, so we can check attribute-based items
        $listItems = $this->fetchRows("
            SELECT lli.id, lli.name, lli.attribute_id, l.entity
            FROM layout_list_item lli
            JOIN layout l ON l.id = lli.layout_id AND l.deleted = false
            WHERE lli.deleted = false
              AND lli.name LIKE 'unit%'
        ");

        foreach ($listItems as $item) {
            if ($this->shouldRename($item['name'], $item['entity'], $item['attribute_id'])) {
                $newName = 'combined' . substr($item['name'], 4);
                $this->updateName('layout_list_item', $item['id'], $newName);
            }
        }

        // layout_row_item has no attribute_id, entity column check only
        $rowItems = $this->fetchRows("
            SELECT lri.id, lri.name, l.entity
            FROM layout_row_item lri
            JOIN layout_section ls ON ls.id = lri.section_id AND ls.deleted = false
            JOIN layout l ON l.id = ls.layout_id AND l.deleted = false
            WHERE lri.deleted = false
              AND lri.name LIKE 'unit%'
        ");

        foreach ($rowItems as $item) {
            if ($this->shouldRename($item['name'], $item['entity'], null)) {
                $newName = 'combined' . substr($item['name'], 4);
                $this->updateName('layout_row_item', $item['id'], $newName);
            }
        }
    }

    private function shouldRename(string $name, ?string $entity, ?string $attributeId): bool
    {
        if (strlen($name) <= 4) {
            return false;
        }

        $fieldName = lcfirst(substr($name, 4)); // "unitHeight" → "height"

        if (!empty($attributeId)) {
            return $this->attributeMatchesFieldName($attributeId, $fieldName);
        }

        if (empty($entity)) {
            return false;
        }

        $tableName  = Util::toUnderScore(lcfirst($entity));
        $columnName = Util::toUnderScore($fieldName);
        $schema     = $this->getSchema();

        return $schema->hasTable($tableName)
            && $schema->getTable($tableName)->hasColumn($columnName);
    }

    private function attributeMatchesFieldName(string $attributeId, string $fieldName): bool
    {
        try {
            $stmt = $this->getPDO()->prepare("
                SELECT id FROM attribute
                WHERE id = :id
                  AND (code = :fieldName OR id = :fieldName)
                  AND deleted = false
            ");
            $stmt->execute([':id' => $attributeId, ':fieldName' => $fieldName]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getSchema(): Schema
    {
        if ($this->currentSchema === null) {
            $this->currentSchema = $this->getCurrentSchema();
        }
        return $this->currentSchema;
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }

    private function fetchRows(string $sql): array
    {
        try {
            return $this->getPDO()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function updateName(string $table, string $id, string $newName): void
    {
        try {
            $stmt = $this->getPDO()->prepare("UPDATE {$table} SET name = :name WHERE id = :id");
            $stmt->execute([':name' => $newName, ':id' => $id]);
        } catch (\Throwable $e) {
        }
    }
}
