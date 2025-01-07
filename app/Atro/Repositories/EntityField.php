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
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Language;
use Espo\Core\DataManager;
use Espo\ORM\Entity as OrmEntity;

class EntityField extends ReferenceData
{
    protected function getAllItems(array $params = []): array
    {
        $entities = [];

        $entityName = $params['whereClause'][0]['entityId='] ?? null;

        if (!empty($entityName)) {
            $entities[] = $entityName;
        } else {
            foreach ($this->getEntityManager()->getRepository('Entity')->find() as $entity) {
                $entities[] = $entity->get('code');
            }
        }

        $boolFields = [];
        foreach ($this->getMetadata()->get(['entityDefs', 'EntityField', 'fields']) as $field => $defs) {
            if ($defs['type'] === 'bool') {
                $boolFields[] = $field;
            }
        }

        $items = [];
        foreach ($entities as $entityName) {
            foreach ($this->getMetadata()->get(['entityDefs', $entityName, 'fields'], []) as $fieldName => $fieldDefs) {
                if (!empty($fieldDefs['emHidden'])) {
                    continue;
                }

                foreach ($boolFields as $boolField) {
                    $fieldDefs[$boolField] = !empty($fieldDefs[$boolField]);
                }

                if (in_array($fieldDefs['type'], ['link', 'linkMultiple'])) {
                    $linkDefs = $this->getMetadata()->get(['entityDefs', $entityName, 'links', $fieldName], []);
                    if ($fieldDefs['type'] === 'linkMultiple') {
                        $fieldDefs['relationType'] = !empty($linkDefs['relationName']) ? 'manyToMany' : 'oneToMany';
                        $fieldDefs['relationName'] = $linkDefs['relationName'] ?? null;
                        $fieldDefs['linkMultipleFieldForeign'] = empty($fieldDefs['noLoad']);
                    }
                    if (!empty($linkDefs['entity'])) {
                        $fieldDefs['foreignEntityId'] = $linkDefs['entity'];
                        $fieldDefs['foreignEntityName'] = $this->translate($linkDefs['entity'], 'scopeNames');
                    }
                    $fieldDefs['foreignCode'] = $linkDefs['foreign'] ?? null;
                }

                $items[] = array_merge($fieldDefs, [
                    'id'          => "{$entityName}_{$fieldName}",
                    'code'        => $fieldName,
                    'name'        => $this->translate($fieldName, 'fields', $entityName),
                    'entityId'    => $entityName,
                    'entityName'  => $this->translate($entityName, 'scopeNames'),
                    'tooltipText' => !empty($fieldDefs['tooltip']) ? $this->translate($fieldName, 'tooltips',
                        $entityName) : null,
                    'tooltipLink' => !empty($fieldDefs['tooltip']) && !empty($fieldDefs['tooltipLink']) ? $fieldDefs['tooltipLink'] : null,
                ]);
            }
        }

        return $items;
    }

    public function validateUnique(OrmEntity $entity): void
    {
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

        $loadedData = json_decode(json_encode($this->getMetadata()->loadData(true)), true);

        $this->updateField($entity, $loadedData);

        return true;
    }

    protected function updateField(OrmEntity $entity, array $loadedData): void
    {
        $saveMetadata = $entity->isNew();
        $saveLanguage = $entity->isNew();

        if ($entity->isAttributeChanged('tooltipText') || $entity->isAttributeChanged('tooltipLink')) {
            $entity->set('tooltip', !empty($entity->get('tooltipText')) || !empty($entity->get('tooltipLink')));
        }

        if ($entity->isNew()) {
            if ($entity->get('type') === 'link') {
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

        foreach ($entity->toArray() as $field => $value) {
            if (!$entity->isAttributeChanged($field) || in_array($field, ['id', 'code'])) {
                continue;
            }

            if (in_array($field, ['name'])) {
                $this
                    ->getLanguage()
                    ->set($entity->get('entityId'), 'fields', $entity->get('code'), $entity->get($field));
                $saveLanguage = true;
            } elseif ($field === 'tooltipText') {
                $this
                    ->getLanguage()
                    ->set($entity->get('entityId'), 'tooltips', $entity->get('code'), $value);
                $saveLanguage = true;
            } elseif ($field === 'linkMultipleFieldForeign') {
                $this->getMetadata()->set('entityDefs', $entity->get('entityId'), [
                    'fields' => [
                        $entity->get('code') => [
                            'noLoad'               => empty($entity->get($field)),
                            'layoutDetailDisabled' => empty($entity->get($field)),
                            'massUpdateDisabled'   => empty($entity->get($field))
                        ]
                    ]
                ]);
            } else {
                $loadedVal = $loadedData['entityDefs'][$entity->get('code')][$field] ?? null;
                if ($this->getMetadata()->get(['entityDefs', 'EntityField', 'fields', $field, 'type']) === 'bool') {
                    $loadedVal = !empty($loadedVal);
                }
                if ($loadedVal === $entity->get($field)) {
                    $this->getMetadata()->delete('entityDefs', $entity->get('entityId'),
                        ["fields.{$entity->get('code')}.{$field}"]);
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
        $scope = $entity->get('entityId');
        $name = $entity->get('code');

        if (empty($this->getMetadata()->get("entityDefs.$scope.fields.$name.isCustom"))) {
            return false;
        }

        $this->getMetadata()->delete('entityDefs', $scope, ["fields.$name", "links.$name"]);
        $this->getMetadata()->save();
        $this->getDataManager()->rebuild();

        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('language');
    }

    protected function translate(string $label, ?string $category = 'labels', ?string $scope = 'Global'): string
    {
        return $this->getLanguage()->translate($label, $category, $scope);
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }
}
