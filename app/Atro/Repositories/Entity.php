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

use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity as OrmEntity;

class Entity extends ReferenceData
{
    protected function getAllItems(array $params = []): array
    {
        $boolFields = [];
        foreach ($this->getMetadata()->get(['entityDefs', 'Entity', 'fields']) as $field => $defs) {
            if ($defs['type'] === 'bool') {
                $boolFields[] = $field;
            }
        }

        $items = [];
        foreach ($this->getMetadata()->get('scopes', []) as $code => $row) {
            if (!empty($row['emHidden'])) {
                continue;
            }

            foreach ($boolFields as $boolField) {
                $row[$boolField] = !empty($row[$boolField]);
            }

            $items[] = array_merge($row, [
                'id'               => $code,
                'code'             => $code,
                'name'             => $this->getLanguage()->translate($code, 'scopeNames'),
                'namePlural'       => $this->getLanguage()->translate($code, 'scopeNamesPlural'),
                'iconClass'        => $this->getMetadata()->get(['clientDefs', $code, 'iconClass']),
                'kanbanViewMode'   => $this->getMetadata()->get(['clientDefs', $code, 'kanbanViewMode']),
                'color'            => $this->getMetadata()->get(['clientDefs', $code, 'color']),
                'sortBy'           => $this->getMetadata()->get(['entityDefs', $code, 'collection', 'sortBy']),
                'sortDirection'    => $this->getMetadata()
                    ->get(['entityDefs', $code, 'collection', 'asc']) ? 'asc' : 'desc',
                'textFilterFields' => $this->getMetadata()
                    ->get(['entityDefs', $code, 'collection', 'textFilterFields']),
            ]);
        }

        return $items;
    }

    public function insertEntity(OrmEntity $entity): bool
    {
        echo '<pre>';
        print_r('insertEntity');
        die();

        return true;
    }

    public function updateEntity(OrmEntity $entity): bool
    {
        $saveMetadata = false;
        $saveLanguage = false;

        $isCustom = !empty($this->getMetadata()->get(['scopes', $entity->get('code'), 'isCustom']));

        $loadedData = json_decode(json_encode($this->getMetadata()->loadData(true)), true);

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
        }

        if ($saveLanguage) {
            $this->getLanguage()->save();
            if ($isCustom) {
                if ($this->getLanguage()->getLanguage() !== $this->getBaseLanguage()->getLanguage()) {
                    $this->getBaseLanguage()->save();
                }
            }
        }

        return true;
    }

    public function deleteEntity(OrmEntity $entity): bool
    {
        echo '<pre>';
        print_r('deleteEntity');
        die();

        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('baseLanguage');
    }

    protected function getLanguage(): \Atro\Core\Utils\Language
    {
        return $this->getInjection('language');
    }

    protected function getBaseLanguage(): \Atro\Core\Utils\Language
    {
        return $this->getInjection('baseLanguage');
    }
}
