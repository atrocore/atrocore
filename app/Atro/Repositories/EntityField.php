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
use Atro\Core\Templates\Repositories\ReferenceData;
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

                $items[] = array_merge($fieldDefs, [
                    'id'          => "{$entityName}_{$fieldName}",
                    'code'        => $fieldName,
                    'name'        => $this->getLanguage()->translate($fieldName, 'fields', $entityName),
                    'entityId'    => $entityName,
                    'entityName'  => $this->getLanguage()->translate($entityName, 'scopeNames'),
                    'tooltipText' => !empty($fieldDefs['tooltip']) ?
                        $this->getLanguage()->translate($fieldName, 'tooltips', $entityName) : null,
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
//        $fieldDefs['isCustom'] = true;

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

        if ($entity->isAttributeChanged('entityId')) {
            throw new BadRequest("Entity cannot be changed.");
        }

        $loadedData = json_decode(json_encode($this->getMetadata()->loadData(true)), true);

        $this->updateField($entity, $loadedData);

        return true;
    }

    protected function updateField(OrmEntity $entity, array $loadedData): void
    {
        $saveMetadata = false;
        $saveLanguage = false;

        if ($entity->isAttributeChanged('tooltipText') || $entity->isAttributeChanged('tooltipLink')) {
            $entity->set('tooltip', !empty($entity->get('tooltipText')) || !empty($entity->get('tooltipLink')));
        }

        foreach ($entity->toArray() as $field => $value) {
            if (!$entity->isAttributeChanged($field) || in_array($field, ['id', 'code'])) {
                continue;
            }

            if (in_array($field, ['name'])) {
//                $category = $field === 'namePlural' ? 'scopeNamesPlural' : 'scopeNames';
//                $this->getLanguage()->set('Global', $category, $entity->get('code'), $entity->get($field));
//                $saveLanguage = true;
            } elseif ($field === 'tooltipText') {
                $this->getLanguage()->set($entity->get('entityId'), 'tooltips', $entity->get('code'), $value);
                $saveLanguage = true;
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
//            $this->getDataManager()->rebuild();
        }

        if ($saveLanguage) {
            $this->getLanguage()->save();
        }
    }

    public function deleteEntity(OrmEntity $entity): bool
    {
        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
    }

    protected function getLanguage(): \Atro\Core\Utils\Language
    {
        return $this->getInjection('language');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }
}
