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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Conflict;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\DataManager;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity as OrmEntity;

class EntityField extends ReferenceData
{
    protected ?array $boolFields = null;

    protected function getEntityById($id)
    {
        $parts = explode("_", $id);
        if (count($parts) !== 2) {
            return null;
        }

        $item = $this->prepareItem($parts[0], $parts[1]);
        if (!empty($item)) {
            $entity = $this->entityFactory->create($this->entityName);
            $entity->set($item);
            $this->prepareVirtualBoolFields($entity);
            $entity->setAsFetched();

            return $entity;
        }

        return null;
    }

    protected function prepareItem(string $entityName, string $fieldName, array $fieldDefs = null): ?array
    {
        if (empty($fieldDefs)) {
            $fieldDefs = $this->getMetadata()->get("entityDefs.$entityName.fields.$fieldName");
        }

        if(empty($fieldDefs)) {
            return null;
        }

        if (!empty($fieldDefs['emHidden'])) {
            return null;
        }

        if (in_array($fieldName, ['id', 'createdAt', 'createdBy', 'modifiedAt', 'modifiedBy', 'followers'])) {
            $fieldDefs['customizable'] = false;
        }

        $fieldDefs['customizable'] = !(array_key_exists('customizable', $fieldDefs) && $fieldDefs['customizable'] === false);

        if ($this->boolFields === null) {
            $this->boolFields = [];
            foreach ($this->getMetadata()->get(['entityDefs', 'EntityField', 'fields']) as $field => $defs) {
                if ($defs['type'] === 'bool') {
                    $this->boolFields[] = $field;
                }
            }
        }

        foreach ($this->boolFields as $boolField) {
            $fieldDefs[$boolField] = !empty($fieldDefs[$boolField]);
        }

        if (in_array($fieldDefs['type'], ['link', 'linkMultiple'])) {
            $linkDefs = $this->getMetadata()->get(['entityDefs', $entityName, 'links', $fieldName], []);
            if ($fieldDefs['type'] === 'linkMultiple') {
                $fieldDefs['relationType'] = !empty($linkDefs['relationName']) ? 'manyToMany' : 'oneToMany';
                $fieldDefs['relationName'] = $linkDefs['relationName'] ?? null;
                $fieldDefs['linkMultipleField'] = empty($fieldDefs['noLoad']);
            }
            if (!empty($linkDefs['entity'])) {
                $fieldDefs['foreignEntityId'] = $linkDefs['entity'];
                $fieldDefs['foreignEntityName'] = $this->translate($linkDefs['entity'], 'scopeNames');
            } else {
                $fieldDefs = $this->getMetadata()->get(['entityDefs', $entityName, 'fields', $fieldName], []);

                if (!empty($fieldDefs['entity'])) {
                    $foreignScope = $this->getMetadata()->get(['scopes', $fieldDefs['entity']], []);

                    if (!empty($foreignScope) && !empty($foreignScope['type']) && $foreignScope['type'] == 'ReferenceData') {
                        $fieldDefs['foreignEntityId'] = $fieldDefs['entity'];
                        $fieldDefs['foreignEntityName'] = $this->translate($fieldDefs['entity'], 'scopeNames');
                    }
                }
            }
            $fieldDefs['foreignCode'] = $linkDefs['foreign'] ?? null;
        }

        $label = $this->translate($fieldName, 'fields', $entityName);
        if (in_array($fieldDefs['type'], ['int', 'float', 'varchar']) && !empty($fieldDefs['measureId'])) {
            $label = $this->translate('unit' . ucfirst($fieldName), 'fields', $entityName);
        }

        return array_merge($fieldDefs, [
            'id'                  => "{$entityName}_{$fieldName}",
            'code'                => $fieldName,
            'name'                => $label,
            'entityId'            => $entityName,
            'entityName'          => $this->translate($entityName, 'scopeNames'),
            'tooltipText'         => $this->translate($fieldName, 'tooltips', $entityName),
            'conditionalRequired' => $this->getMetadata()->get("entityDefs.$entityName.fields.$fieldName.conditionalProperties.required"),
            'conditionalReadOnly' => $this->getMetadata()->get("entityDefs.$entityName.fields.$fieldName.conditionalProperties.readOnly"),
            'conditionalVisible' => $this->getMetadata()->get("entityDefs.$entityName.fields.$fieldName.conditionalProperties.visible"),
            'multilangField'      => $this->getMetadata()->get("entityDefs.$entityName.fields.$fieldName.multilangField"),
        ]);
    }

    protected function getAllItems(array $params = []): array
    {
        $entities = [];

        $entityName = null;
        foreach ($params['whereClause'] ?? [] as $item) {
            if (!empty($item['entityId='])) {
                $entityName = $item['entityId='];
            } elseif (!empty($item['entityId'])) {
                $entityName = $item['entityId'];
            }
        }

        if (!empty($entityName)) {
            $entities[] = $entityName;
        } else {
            foreach ($this->getMetadata()->get('scopes') as $scope => $scopeDefs) {
                if (!empty($scopeDefs['emHidden'])) {
                    continue;
                }
                $entities[] = $scope;
            }
        }

        $types = null;
        foreach ($params['whereClause'] ?? [] as $v) {
            if (!empty($v['type']) && is_array($v['type'])) {
                $types = $v['type'];
            }
        }

        $items = [];
        foreach ($entities as $entityName) {
            $items[] = [
                'id'         => "{$entityName}_id",
                'code'       => 'id',
                'name'       => 'ID',
                'type'       => 'varchar',
                'required'   => false,
                'readOnly'   => true,
                'entityId'   => $entityName,
                'entityName' => $this->translate($entityName, 'scopeNames')
            ];
            foreach ($this->getMetadata()->get(['entityDefs', $entityName, 'fields'], []) as $fieldName => $fieldDefs) {
                if (is_array($types) && !in_array($fieldDefs['type'], $types)) {
                    continue;
                }

                if (!empty($fieldDefs['multilangField'])) {
                    continue;
                }

                if (!empty($item = $this->prepareItem($entityName, $fieldName, $fieldDefs))) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    public function validateCode(OrmEntity $entity): void
    {
    }

    public function validateUnique(OrmEntity $entity): void
    {
    }

    protected function beforeSave(OrmEntity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($this->getMetadata()->get("scopes.{$entity->get('entityId')}.hasAttribute")) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')
                ->where([
                    'entityId' => $entity->get('entityId'),
                    'code'     => $entity->get('code')
                ])
                ->findOne();

            if (!empty($attribute)) {
                throw new BadRequest("Attribute with such code exists for the {$entity->get('entityId')}.");
            }
        }

        if ($this->getMetadata()->get("entityDefs.{$entity->get('entityId')}.fields.{$entity->get('code')}.multilangField")) {
            throw new Forbidden();
        }

        if ($this->getMetadata()->get("scopes.{$entity->get('entityId')}.customizable") === false) {
            throw new Forbidden();
        }

        if ($entity->get('type') == 'linkMultiple') {
            if (
                ($this->getMetadata()->get("scopes.{$entity->get('entityId')}.type") === 'ReferenceData' && $entity->isNew())
                || $this->getMetadata()->get("scopes.{$entity->get('foreignEntityId')}.type") === 'ReferenceData'
            ) {
                throw new BadRequest("It is not possible to create a relationship with an entity of type 'ReferenceData'.");
            }
        }

        if (!$entity->isNew() && $entity->get('type') === 'bool' && !empty($entity->get('notNull')) && $entity->isAttributeChanged('notNull')) {
            $connection = $this->getEntityManager()->getConnection();
            $entityName = $entity->get('entityId');
            $type = $this->getMetadata()->get("scopes.{$entityName}.type");

            if (!empty($type) && $type !== 'ReferenceData') {
                $tableName = $this->getEntityManager()->getMapper()->toDb($entityName);
                $column = $this->getEntityManager()->getMapper()->toDb($entity->get('code'));
                $connection->createQueryBuilder()
                    ->update($connection->quoteIdentifier($tableName))
                    ->set($column, ':false')
                    ->where("$column is null")
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeStatement();
            }
        }

        if (!empty($entity->get('default'))) {
            if ($entity->get('type') === 'varchar' && $entity->get('defaultValueType') !== 'script') {
                $maxLength = $this->getMetadata()->get("entityDefs.{$entity->get('entityId')}.fields.{$entity->get('code')}.len", 255);

                if (strlen($entity->get('default')) > $maxLength) {
                    throw new BadRequest("Default value is to long. Maximum length is {$maxLength}.");
                }
            }

            if (!empty($entity->get('allowedOptions'))) {
                switch ($entity->get('type')) {
                    case 'extensibleEnum':
                        if (!in_array($entity->get('default'), $entity->get('allowedOptions'))) {
                            throw new BadRequest(sprintf(
                                $this->getLanguage()->translate('notAllowedOption', 'exceptions'),
                                $entity->get('defaultName') ?? $entity->get('default'),
                                $this->getLanguage()->translate('default', 'fields', 'EntityField')
                            ));
                        }
                        break;
                    case 'extensibleMultiEnum':
                        foreach ($entity->get('default') as $optionId) {
                            if (!in_array($optionId, $entity->get('allowedOptions'))) {
                                $defaultNames = $entity->get('defaultNames') ?? new \stdClass();
                                throw new BadRequest(sprintf(
                                    $this->getLanguage()->translate('notAllowedOption', 'exceptions'),
                                    $defaultNames->{$optionId} ?? $optionId,
                                    $this->getLanguage()->translate('default', 'fields', 'EntityField')
                                ));
                            }
                        }
                        break;
                }
            }
        }
    }

    protected function afterSave(OrmEntity $entity, array $options = [])
    {
        $this->updateEntityFromVirtualFields($entity);
        parent::afterSave($entity, $options);
    }

    protected function beforeRemove(OrmEntity $entity, array $options = [])
    {
        if ($this->getMetadata()->get("scopes.{$entity->get('entityId')}.customizable") === false) {
            throw new Forbidden();
        }

        parent::beforeRemove($entity, $options);
    }

    public function insertEntity(OrmEntity $entity): bool
    {
        if (!preg_match('/^[a-z][A-Za-z0-9]*$/', $entity->get('code'))) {
            throw new BadRequest("Code is invalid.");
        }

        if ($this->getMetadata()->get("entityDefs.{$entity->get('entityId')}.fields.{$entity->get('code')}")) {
            throw new Conflict("Entity field '{$entity->get('code')}' is already exists.");
        }

        if (in_array($entity->get('type'), ['link', 'linkMultiple'])) {
            if (
                empty($entity->get('foreignCode'))
                || !preg_match('/^[a-z][A-Za-z0-9]*$/', $entity->get('foreignCode'))
            ) {
                throw new BadRequest("Foreign Code is invalid.");
            }

            if ($this->getMetadata()->get("entityDefs.{$entity->get('foreignEntityId')}.fields.{$entity->get('foreignCode')}")) {
                throw new Conflict("Entity field '{$entity->get('foreignCode')}' is already exists.");
            }

            if ($entity->get('type') === 'linkMultiple') {
                if (
                    empty($entity->get('relationName'))
                    || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $entity->get('relationName'))
                ) {
                    throw new BadRequest("Middle Table Name is invalid.");
                }
            }
        }

        $entity->id = "{$entity->get('entityId')}_{$entity->get('code')}";
        $entity->set('isCustom', true);

        // update metadata
        $this->updateField($entity, []);

        return true;
    }

    public function updateEntity(OrmEntity $entity): bool
    {
        if ($entity->isAttributeChanged('code')) {
            throw new BadRequest("Code cannot be changed.");
        }

        if ($entity->isAttributeChanged('foreignCode')) {
            throw new BadRequest("Foreign Code cannot be changed.");
        }

        if ($entity->isAttributeChanged('type')) {
            throw new BadRequest("Type cannot be changed.");
        }

        if ($entity->isAttributeChanged('entityId')) {
            throw new BadRequest("Entity cannot be changed.");
        }

        if ($entity->isAttributeChanged('foreignEntityId')) {
            throw new BadRequest("Foreign Entity cannot be changed.");
        }

        if ($entity->isAttributeChanged('relationName')) {
            throw new BadRequest("Middle Table Name cannot be changed.");
        }

        $loadedData = json_decode(json_encode($this->getMetadata()->loadData()), true);

        $this->updateField($entity, $loadedData);

        return true;
    }

    protected function updateField(OrmEntity $entity, array $loadedData): void
    {
        $saveMetadata = $entity->isNew();
        $saveLanguage = $entity->isNew();

        if ($entity->isNew()) {
            if ($entity->get('type') === 'link') {
                $foreignType = $this->getMetadata()->get(['scopes', $entity->get('foreignEntityId'), 'type']);

                if ($foreignType !== 'ReferenceData') {
                    $this->getMetadata()->set('entityDefs', $entity->get('entityId'), [
                        'links' => [
                            $entity->get('code') => [
                                'type'    => 'belongsTo',
                                'foreign' => $entity->get('foreignCode'),
                                'entity'  => $entity->get('foreignEntityId'),
                            ]
                        ]
                    ]);

                    $this->getMetadata()->set('entityDefs', $entity->get('foreignEntityId'), [
                        'fields' => [
                            $entity->get('foreignCode') => [
                                'type'                 => 'linkMultiple',
                                'noLoad'               => true,
                                'layoutDetailDisabled' => true,
                                'massUpdateDisabled'   => true,
                                'isCustom'             => true
                            ]
                        ]
                    ]);
                    $this->getMetadata()->set('entityDefs', $entity->get('foreignEntityId'), [
                        'links' => [
                            $entity->get('foreignCode') => [
                                'type'    => 'hasMany',
                                'foreign' => $entity->get('code'),
                                'entity'  => $entity->get('entityId'),
                            ]
                        ]
                    ]);
                } else {
                    $this->getMetadata()->set('entityDefs', $entity->get('entityId'), [
                        'fields' => [
                            $entity->get('code') => [
                                'entity' => $entity->get('foreignEntityId'),
                            ]
                        ]
                    ]);
                }
            } elseif ($entity->get('type') === 'linkMultiple') {
                if ($entity->get('relationType') === 'manyToMany') {
                    $this->getMetadata()->set('entityDefs', $entity->get('entityId'), [
                        'links' => [
                            $entity->get('code') => [
                                'type'         => 'hasMany',
                                'foreign'      => $entity->get('foreignCode'),
                                'relationName' => $entity->get('relationName'),
                                'entity'       => $entity->get('foreignEntityId'),
                            ]
                        ]
                    ]);

                    $this->getMetadata()->set('entityDefs', $entity->get('foreignEntityId'), [
                        'fields' => [
                            $entity->get('foreignCode') => [
                                'type'                 => 'linkMultiple',
                                'noLoad'               => true,
                                'layoutDetailDisabled' => true,
                                'massUpdateDisabled'   => true,
                                'isCustom'             => true
                            ]
                        ]
                    ]);
                    $this->getMetadata()->set('entityDefs', $entity->get('foreignEntityId'), [
                        'links' => [
                            $entity->get('foreignCode') => [
                                'type'         => 'hasMany',
                                'foreign'      => $entity->get('code'),
                                'relationName' => $entity->get('relationName'),
                                'entity'       => $entity->get('entityId'),
                            ]
                        ]
                    ]);
                } else {
                    $this->getMetadata()->set('entityDefs', $entity->get('entityId'), [
                        'links' => [
                            $entity->get('code') => [
                                'type'    => 'hasMany',
                                'foreign' => $entity->get('foreignCode'),
                                'entity'  => $entity->get('foreignEntityId'),
                            ]
                        ]
                    ]);

                    $this->getMetadata()->set('entityDefs', $entity->get('foreignEntityId'), [
                        'fields' => [
                            $entity->get('foreignCode') => [
                                'type' => 'link'
                            ]
                        ]
                    ]);
                    $this->getMetadata()->set('entityDefs', $entity->get('foreignEntityId'), [
                        'links' => [
                            $entity->get('foreignCode') => [
                                'type'    => 'belongsTo',
                                'foreign' => $entity->get('code'),
                                'entity'  => $entity->get('entityId'),
                            ]
                        ]
                    ]);
                }
            }
        }

        $commonFields = ['tooltipLink', 'tooltip', 'type', 'auditableEnabled', 'auditableDisabled', 'isCustom', 'modifiedExtendedDisabled'];
        $typeFields = array_column($this->getMetadata()->get("fields.{$entity->get('type')}.params", []), 'name');
        if (in_array($entity->get('type'), ['enum', 'multiEnum'])) {
            $typeFields[] = 'optionColors';
        }

        foreach (array_merge($commonFields, $typeFields) as $field) {
            $fieldType = $this->getMetadata()->get("entityDefs.EntityField.fields.{$field}.type");
            if ($fieldType === 'link') {
                $field .= 'Id';
            }

            if (!$entity->isAttributeChanged($field)) {
                continue;
            }

            $loadedVal = $loadedData['entityDefs'][$entity->get('entityId')]['fields'][$entity->get('code')][$field] ?? null;

            if ($loadedVal === $entity->get($field)) {
                $this->getMetadata()->delete('entityDefs', $entity->get('entityId'), [
                    "fields.{$entity->get('code')}.{$field}"
                ]);
            } else {
                $this->getMetadata()->set('entityDefs', $entity->get('entityId'), [
                    'fields' => [
                        $entity->get('code') => [
                            $field => $entity->get($field)
                        ]
                    ]
                ]);
            }
            $saveMetadata = true;
        }

        if ($entity->isAttributeChanged('name')) {
            $this->getLanguage()
                ->set($entity->get('entityId'), 'fields', $entity->get('code'), $entity->get('name'));
            $saveLanguage = true;
        }

        if ($entity->isAttributeChanged('tooltipText')) {
            $this->getLanguage()
                ->set($entity->get('entityId'), 'tooltips', $entity->get('code'), $entity->get('tooltipText'));
            $saveLanguage = true;
        }

        if ($entity->get('type') === 'linkMultiple' && $entity->isAttributeChanged('linkMultipleField')) {
            $this->getMetadata()->set('entityDefs', $entity->get('entityId'), [
                'fields' => [
                    $entity->get('code') => [
                        'noLoad'               => empty($entity->get('linkMultipleField')),
                        'layoutDetailDisabled' => empty($entity->get('linkMultipleField')),
                        'massUpdateDisabled'   => empty($entity->get('linkMultipleField'))
                    ]
                ]
            ]);
            $saveMetadata = true;
        }

        $conditionalProperties = $this->getMetadata()->get("entityDefs.{$entity->get('entityId')}.fields.{$entity->get('code')}.conditionalProperties") ?? [];

        if ($entity->isAttributeChanged('conditionalRequired')) {
            $conditionalProperties['required'] = $entity->get('conditionalRequired');
        }

        if ($entity->isAttributeChanged('conditionalReadOnly')) {
            $conditionalProperties['visible'] = $entity->get('conditionalReadOnly');
        }

        if ($entity->isAttributeChanged('conditionalVisible')) {
            $conditionalProperties['visible'] = $entity->get('conditionalVisible');
        }

        if (!empty($conditionalProperties)) {
            $this->getMetadata()->set('entityDefs', $entity->get('entityId'), [
                'fields' => [
                    $entity->get('code') => [
                        'conditionalProperties' => $conditionalProperties,
                    ],
                ],
            ]);
            $saveMetadata = true;
        }

        if ($saveMetadata) {
            $this->getMetadata()->save();
            $this->getDataManager()->rebuild();
        }

        if ($saveLanguage) {
            $this->getLanguage()->save();
        }
    }

    public function deleteEntity(OrmEntity $entity): bool
    {
        $this->deleteFromMetadata($entity);
        $this->getMetadata()->save();
        $this->getDataManager()->rebuild();

        return true;
    }

    public function deleteFromMetadata(OrmEntity $entity): void
    {
        $scope = $entity->get('entityId');
        $name = $entity->get('code');


        $foreignScope = $this->getMetadata()->get("entityDefs.$scope.links.$name.entity");
        if (!empty($foreignScope)) {
            $foreign = $this->getMetadata()->get("entityDefs.$scope.links.$name.foreign");
            if (!empty($foreign)) {
                $this->getMetadata()->delete('entityDefs', $foreignScope, ["fields.$foreign", "links.$foreign"]);
            }
        }

        $this->getMetadata()->delete('entityDefs', $scope, ["fields.$name", "links.$name"]);
    }

    protected function updateEntityFromVirtualFields(OrmEntity $entity): void
    {
        $entityEntity = $this->getEntityManager()->getEntity('Entity', $entity->get('entityId'));
        $virtualToEntityFields = [
            "isNonComparable"         => "nonComparableFields",
            "isDuplicatableRelation"  => "duplicatableRelations",
            "isUninheritableField"    => "unInheritedFields",
            "isUninheritableRelation" => "unInheritedRelations",
            "modifiedExtendedEnabled" => "modifiedExtendedRelations"
        ];

        foreach ($virtualToEntityFields as $field => $entityField) {
            if ($entity->isAttributeChanged($field)) {
                $values = $entityEntity->get($entityField) ?? [];
                if (!empty($entity->get($field))) {
                    if (!in_array($entity->get('code'), $values)) {
                        $values[] = $entity->get('code');
                    }
                } else {
                    $oldValues = $values;
                    $values = [];
                    foreach ($oldValues as $value) {
                        if ($value === $entity->get('code')) {
                            continue;
                        }
                        $values[] = $value;
                    }
                }
                $entityEntity->set($entityField, $values);
                $this->getEntityManager()->getRepository('Entity')->save($entityEntity);
            }
        }
    }

    protected function prepareVirtualBoolFields(OrmEntity $entity): void
    {
        $entityEntity = $this->getEntityManager()->getEntity('Entity', $entity->get('entityId'));
        $virtualToEntityFields = [
            "isNonComparable"         => "nonComparableFields",
            "isDuplicatableRelation"  => "duplicatableRelations",
            "isUninheritableField"    => "unInheritedFields",
            "isUninheritableRelation" => "unInheritedRelations",
            "modifiedExtendedEnabled" => "modifiedExtendedRelations"
        ];

        foreach ($virtualToEntityFields as $field => $entityField) {
            $entity->set($field, in_array($entity->get('code'), $entityEntity->get($entityField) ?? []));
        }

        $defaultRelationScopeAudited = [];
        foreach ($this->getMetadata()->get(['scopes']) as $scopeKey => $scopeDefs) {
            if (!empty($scopeDefs['defaultRelationAudited'])) {
                $defaultRelationScopeAudited[] = $scopeKey;
            }
        }

        // we set auditableEnabled to true for File, channel and category is nothing was defined
        if (in_array($entity->get('foreignEntityId'), $defaultRelationScopeAudited)) {
            $fieldDefs = $this->getMetadata()->get(['entityDefs', $entity->get('entityId'), 'fields', $entity->get('code')]);
            if (!isset($fieldDefs['auditableEnabled'])) {
                $entity->set('auditableEnabled', true);
            }
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }
}
