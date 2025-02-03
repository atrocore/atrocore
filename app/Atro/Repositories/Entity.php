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
use Atro\Core\Utils\Util;
use Atro\Core\DataManager;
use Espo\ORM\Entity as OrmEntity;
use Espo\ORM\EntityCollection;

class Entity extends ReferenceData
{
    public const RESERVED_WORDS = [
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'common'
    ];

    protected ?array $boolFields = null;

    protected function getEntityById($id)
    {
        $item = $this->prepareItem($id);
        if (!empty($item)) {
            $entity = $this->entityFactory->create($this->entityName);
            $entity->set($item);
            $entity->setAsFetched();

            return $entity;
        }

        return null;
    }

    public function findRelated(OrmEntity $entity, string $link, array $selectParams): EntityCollection
    {
        if ($link === 'fields') {
            $selectParams['whereClause'] = [['entityId=' => $entity->get('id')]];
            return $this->getEntityManager()->getRepository('EntityField')->find($selectParams);
        }

        return parent::findRelated($entity, $link, $selectParams);
    }

    public function countRelated(OrmEntity $entity, string $relationName, array $params = []): int
    {
        if ($relationName === 'fields') {
            $params['offset'] = 0;
            $params['limit'] = \PHP_INT_MAX;
            return count($this->findRelated($entity, $relationName, $params));
        }

        return parent::countRelated($entity, $relationName, $params);
    }

    protected function prepareItem(string $code, array $row = null): ?array
    {
        if ($row === null){
            $row = $this->getMetadata()->get("scopes.$code");
        }

        if (empty($row) || !empty($row['emHidden'])) {
            return null;
        }

        if ($this->boolFields === null) {
            $this->boolFields = [];
            foreach ($this->getMetadata()->get(['entityDefs', 'Entity', 'fields']) as $field => $defs) {
                if ($defs['type'] === 'bool') {
                    $this->boolFields[] = $field;
                }
            }
        }

        foreach ($this->boolFields as $boolField) {
            $row[$boolField] = !empty($row[$boolField]);
        }

        return array_merge($row, [
            'id'                    => $code,
            'code'                  => $code,
            'name'                  => $this->getLanguage()->translate($code, 'scopeNames'),
            'namePlural'            => $this->getLanguage()->translate($code, 'scopeNamesPlural'),
            'iconClass'             => $this->getMetadata()->get(['clientDefs', $code, 'iconClass']),
            'kanbanViewMode'        => $this->getMetadata()->get(['clientDefs', $code, 'kanbanViewMode']),
            'clearDeletedAfterDays' => $this->getMetadata()->get(['scopes', $code, 'clearDeletedAfterDays'], 60),
            'color'                 => $this->getMetadata()->get(['clientDefs', $code, 'color']),
            'sortBy'                => $this->getMetadata()->get(['entityDefs', $code, 'collection', 'sortBy']),
            'sortDirection'         => $this->getMetadata()->get(['entityDefs', $code, 'collection', 'asc']) ? 'asc' : 'desc',
            'textFilterFields'      => $this->getMetadata()->get(['entityDefs', $code, 'collection', 'textFilterFields']),
        ]);
    }

    protected function getAllItems(array $params = []): array
    {
        $scopeTypes = $params['whereClause'][0]['type'] ?? null;

        $items = [];
        foreach ($this->getMetadata()->get('scopes', []) as $code => $row) {
            if (!empty($row['emHidden']) || (!empty($scopeTypes) && !empty($row['type']) && !in_array($row['type'], $scopeTypes))) {
                continue;
            }

            if (!empty($item = $this->prepareItem($code, $row))) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function insertEntity(OrmEntity $entity): bool
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $entity->get('code'))) {
            throw new BadRequest("Code is invalid.");
        }

        if ($this->getMetadata()->get('scopes.' . $entity->get('code'))) {
            throw new Conflict("Entity '{$entity->get('code')}' is already exists.");
        }

        if (in_array(strtolower($entity->get('code')), self::RESERVED_WORDS)) {
            throw new Conflict("Entity name '{$entity->get('code')}' is not allowed.");
        }

        // copy default layouts
        $layoutsPath = CORE_PATH . "/Atro/Core/Templates/Layouts/{$entity->get('type')}";
        if (is_dir($layoutsPath)) {
            Util::createDir("data/layouts/{$entity->get('code')}");
            foreach (scandir($layoutsPath) as $fileName) {
                if (in_array($fileName, ['.', '..']) || !is_file("$layoutsPath/$fileName")) {
                    continue;
                }
                file_put_contents(
                    "data/layouts/{$entity->get('code')}/$fileName",
                    file_get_contents("$layoutsPath/$fileName")
                );
            }
        }

        // copy default metadata
        foreach (['clientDefs', 'entityDefs', 'scopes'] as $type) {
            $contents = file_get_contents(CORE_PATH . "/Atro/Core/Templates/Metadata/{$entity->get('type')}/$type.json");
            if ($entity->get('type') === 'Hierarchy' && $type === 'entityDefs') {
                $contents = str_replace('{entityType}', $entity->get('code'), $contents);
            }
            Util::createDir("data/metadata/{$type}");
            file_put_contents("data/metadata/$type/{$entity->get('code')}.json", $contents);
        }

        $entity->id = $entity->get('code');
        $entity->set('isCustom', true);
        $entity->set('customizable', true);

        // update metadata
        $this->updateScope($entity, [], true);

        // update config
        foreach (['quickCreateList', 'tabList'] as $key) {
            $list = $this->getConfig()->get($key, []);
            if (!in_array($entity->get('code'), $list)) {
                $list[] = $entity->get('code');
            }
            $this->getConfig()->set($key, $list);
        }
        $this->getConfig()->save();

        return true;
    }

    public function updateEntity(OrmEntity $entity): bool
    {
        if ($entity->isAttributeChanged('code')) {
            throw new BadRequest("Code cannot be changed.");
        }

        if ($entity->isAttributeChanged('type')) {
            throw new BadRequest("Type cannot be changed.");
        }

        $loadedData = json_decode(json_encode($this->getMetadata()->loadData(true)), true);
        $isCustom = !empty($this->getMetadata()->get(['scopes', $entity->get('code'), 'isCustom']));

        $this->updateScope($entity, $loadedData, $isCustom);

        return true;
    }

    protected function updateScope(OrmEntity $entity, array $loadedData, bool $isCustom): void
    {
        $saveMetadata = $isCustom;
        $saveLanguage = $isCustom;

        foreach ($entity->toArray() as $field => $value) {
            if (!$entity->isAttributeChanged($field) || in_array($field, ['id', 'code'])) {
                continue;
            }

            if (in_array($field, ['iconClass', 'color', 'kanbanViewMode'])) {
                $loadedVal = $loadedData['clientDefs'][$entity->get('code')][$field] ?? null;
                if ($loadedVal === $entity->get($field)) {
                    $this->getMetadata()->delete('clientDefs', $entity->get('code'), [$field]);
                } else {
                    $this->getMetadata()->set('clientDefs', $entity->get('code'), [$field => $entity->get($field)]);
                }
                $saveMetadata = true;
            } elseif (in_array($field, ['name', 'namePlural'])) {
                $category = $field === 'namePlural' ? 'scopeNamesPlural' : 'scopeNames';
                $this->getLanguage()->set('Global', $category, $entity->get('code'), $entity->get($field));
                if ($isCustom) {
                    $this->getBaseLanguage()->set('Global', $category, $entity->get('code'), $entity->get($field));
                }
                $saveLanguage = true;
            } elseif ($field === 'sortBy') {
                $loadedVal = $loadedData['entityDefs'][$entity->get('code')]['collection']['sortBy'] ?? null;
                if ($loadedVal === $entity->get($field)) {
                    $this->getMetadata()->delete('entityDefs', $entity->get('code'), ['collection.sortBy']);
                } else {
                    $this->getMetadata()->set('entityDefs', $entity->get('code'), [
                        'collection' => [
                            'sortBy' => $entity->get($field)
                        ]
                    ]);
                }
                $saveMetadata = true;
            } elseif ($field === 'sortDirection') {
                $loadedVal = $loadedData['entityDefs'][$entity->get('code')]['collection']['asc'] ?? null;
                $asc = $entity->get($field) === 'asc';
                if ($loadedVal === $asc) {
                    $this->getMetadata()->delete('entityDefs', $entity->get('code'), ['collection.asc']);
                } else {
                    $this->getMetadata()->set('entityDefs', $entity->get('code'), [
                        'collection' => [
                            'asc' => $asc
                        ]
                    ]);
                }
                $saveMetadata = true;
            } elseif ($field === 'textFilterFields') {
                $loadedVal = $loadedData['entityDefs'][$entity->get('code')]['collection']['textFilterFields'] ?? null;
                if ($loadedVal === $entity->get($field)) {
                    $this->getMetadata()->delete('entityDefs', $entity->get('code'), ['collection.textFilterFields']);
                } else {
                    $this->getMetadata()->set('entityDefs', $entity->get('code'), [
                        'collection' => [
                            'textFilterFields' => $entity->get($field)
                        ]
                    ]);
                }
                $saveMetadata = true;
            } else {
                $loadedVal = $loadedData['scopes'][$entity->get('code')][$field] ?? null;
                if ($loadedVal === $entity->get($field)) {
                    $this->getMetadata()->delete('scopes', $entity->get('code'), [$field]);
                } else {
                    $this->getMetadata()->set('scopes', $entity->get('code'), [$field => $entity->get($field)]);
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
            if ($isCustom) {
                if ($this->getLanguage()->getLanguage() !== $this->getBaseLanguage()->getLanguage()) {
                    $this->getBaseLanguage()->save();
                }
            }
        }
    }

    public function beforeSave(OrmEntity $entity, array $options = [])
    {
        if ($this->getMetadata()->get("scopes.{$entity->get('code')}.customizable") === false) {
            throw new Forbidden();
        }

        if ($entity->get('type') === 'Hierarchy' && !empty($entity->get('modifiedExtendedRelations'))) {
            if (in_array('parents', $entity->get('modifiedExtendedRelations')) && in_array('children', $entity->get('modifiedExtendedRelations'))) {
                throw new BadRequest(str_replace(['{parents}', '{children}'],
                    [$this->getLanguage()->translate('parents', 'fields', $entity->get('code')), $this->getLanguage()->translate('children', 'fields', $entity->get('code'))],
                    $this->getLanguage()->translate('parentsAndChildrenShouldNotBeInModifiedExtendedRelations', 'exceptions', 'Entity')));
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function beforeRemove(OrmEntity $entity, array $options = [])
    {
        if (empty($entity->get('isCustom'))) {
            throw new Forbidden();
        }

        if ($this->getMetadata()->get("scopes.{$entity->get('code')}.customizable") === false) {
            throw new Forbidden();
        }

        parent::beforeRemove($entity, $options);
    }

    public function deleteEntity(OrmEntity $entity): bool
    {
        // delete relationships
        $saveMetadata = false;
        foreach ($entity->get('fields') ?? [] as $field) {
            if (in_array($field->get('type'), ['link', 'linkMultiple'])) {
                $this->getEntityManager()->getRepository('EntityField')->deleteFromMetadata($field);
                $saveMetadata = true;
            }
        }
        if ($saveMetadata) {
            $this->getMetadata()->save();
        }

        // delete metadata
        foreach (['clientDefs', 'entityDefs', 'scopes'] as $type) {
            $fileName = "data/metadata/$type/{$entity->get('code')}.json";
            if (file_exists($fileName)) {
                unlink($fileName);
            }
        }

        // delete layouts
        Util::removeDir("data/layouts/{$entity->get('code')}");

        // delete translations
        $labels = $this->getEntityManager()->getRepository('Translation')->find();
        foreach ($labels as $label) {
            if (
                str_starts_with($label->get('code'), "{$entity->get('code')}.")
                && $label->get('module') === 'custom'
                && $label->get('isCustomized')
            ) {
                $this->getEntityManager()->removeEntity($label);
            }
            if (
                $label->get('code') === "Global.scopeNames.{$entity->get('code')}"
                || $label->get('code') === "Global.scopeNamesPlural.{$entity->get('code')}"
            ) {
                $this->getEntityManager()->removeEntity($label);
            }
        }

        $this->getDataManager()->clearCache();

        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('baseLanguage');
        $this->addDependency('dataManager');
    }

    protected function getBaseLanguage(): \Atro\Core\Utils\Language
    {
        return $this->getInjection('baseLanguage');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }
}
