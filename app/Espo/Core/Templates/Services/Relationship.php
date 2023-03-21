<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Espo\Core\Templates\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Services\Record;

class Relationship extends Record
{
    public const VIRTUAL_FIELD_DELIMITER = "_";

    public function getSelectAttributeList($params)
    {
        $list = parent::getSelectAttributeList($params);

        if (!empty($list) && is_array($list)) {
            foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], []) as $field => $fieldDefs) {
                if (empty($fieldDefs['type']) || $fieldDefs['type'] !== 'link') {
                    continue;
                }

                if (!empty($fieldDefs['relationshipField']) && !in_array($field . 'Id', $list)) {
                    $list[] = $field . 'Id';
                }
                if (!empty($fieldDefs['relationshipField']) && !in_array($field . 'Name', $list)) {
                    $list[] = $field . 'Name';
                }
            }
        }

        return $list;
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        if ($this->getMetadata()->get(['scopes', $collection->getEntityName(), 'relationsVirtualFields']) !== false && empty($collection->preparedRelationVirtualFields)) {
            $select = empty($selectParams['select']) ? null : $selectParams['select'];
            $virtualFields = $this->getRelationsVirtualFields($collection->getEntityName(), $select);
            if (!empty($relEntities = $this->getRelationEntities($collection, $virtualFields))) {
                foreach ($collection as $entity) {
                    $this->prepareRelationVirtualFields($entity, $relEntities, $virtualFields);
                }
            }
        }

        parent::prepareCollectionForOutput($collection, $selectParams);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        if ($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'relationsVirtualFields']) !== false && empty($entity->preparedRelationVirtualFields)) {
            $collection = new EntityCollection([$entity], $entity->getEntityName());
            $virtualFields = $this->getRelationsVirtualFields($entity->getEntityName());
            if (!empty($relEntities = $this->getRelationEntities($collection, $virtualFields))) {
                $this->prepareRelationVirtualFields($entity, $relEntities, $virtualFields);
            }
        }

        parent::prepareEntityForOutput($entity);
    }

    public function getRelationsVirtualFields(string $entityName, ?array $select = null): array
    {
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $entityName, 'fields'], []) as $field => $fieldDefs) {
            if ($select !== null && !in_array($field, $select)) {
                continue;
            }

            $parts = explode(self::VIRTUAL_FIELD_DELIMITER, $field);
            if (count($parts) === 2) {
                $result[$field] = $fieldDefs;
                $result[$field]['relationName'] = $parts[0];
                $result[$field]['relationFieldName'] = $parts[1];
            }
        }

        return $result;
    }

    public function getRelationEntities(EntityCollection $collection, array $virtualFields): array
    {
        $relEntities = [];

        foreach ($virtualFields as $field => $fieldDefs) {
            $relEntityName = $this->getMetadata()->get(['entityDefs', $collection->getEntityName(), 'links', $fieldDefs['relationName'], 'entity']);
            if (!isset($relEntities[$fieldDefs['relationName']])) {
                $where = [
                    [
                        'type'      => 'in',
                        'attribute' => 'id',
                        'value'     => array_column($collection->toArray(), $fieldDefs['relationName'] . 'Id')
                    ]
                ];
                $res = $this->getServiceFactory()->create($relEntityName)->findEntities(['where' => $where]);
                if (isset($res['collection'])) {
                    foreach ($res['collection'] as $relEntity) {
                        $relEntities[$fieldDefs['relationName']][$relEntity->get('id')] = $relEntity;
                    }
                }
            }
        }

        return $relEntities;
    }

    public function prepareRelationVirtualFields(Entity $entity, array $relEntities, array $virtualFields): void
    {
        foreach ($virtualFields as $field => $fieldDefs) {
            $relEntity = $relEntities[$fieldDefs['relationName']][$entity->get($fieldDefs['relationName'] . 'Id')];

            switch ($this->getMetadata()->get(['entityDefs', $relEntity->getEntityType(), 'fields', $fieldDefs['relationFieldName'], 'type'])) {
                case 'currency':
                    $entity->set($field, $relEntity->get($fieldDefs['relationFieldName']));
                    $entity->set($field . 'Currency', $relEntity->get($fieldDefs['relationFieldName'] . 'Currency'));
                    break;
                case 'unit':
                    $entity->set($field, $relEntity->get($fieldDefs['relationFieldName']));
                    $entity->set($field . 'Unit', $relEntity->get($fieldDefs['relationFieldName'] . 'Unit'));
                    break;
                case 'link':
                case 'file':
                case 'asset':
                    $entity->set($field . 'Id', $relEntity->get($fieldDefs['relationFieldName'] . 'Id'));
                    $entity->set($field . 'Name', $relEntity->get($fieldDefs['relationFieldName'] . 'Name'));
                    break;
                default:
                    $entity->set($field, $relEntity->get($fieldDefs['relationFieldName']));
            }
        }

        $entity->preparedRelationVirtualFields = true;
    }

    public function createRelationshipEntitiesViaAddRelation(string $entityType, EntityCollection $entities, array $foreignIds): array
    {
        $relationshipEntities = $this->getRelationshipEntities();
        if (count($relationshipEntities) !== 2) {
            throw new BadRequest('Action blocked.');
        }

        $foreignCollection = new EntityCollection();
        foreach ($relationshipEntities as $relationshipEntity) {
            if ($relationshipEntity !== $entityType) {
                $foreignCollection = $this->getEntityManager()->getRepository($relationshipEntity)->where(['id' => $foreignIds])->find();
            }
        }

        $related = 0;
        $notRelated = [];

        foreach ($foreignCollection as $foreignEntity) {
            foreach ($entities as $entity) {
                $input = [];
                foreach ($relationshipEntities as $field => $entityType) {
                    if ($entityType === $entity->getEntityType()) {
                        $input[$field . 'Id'] = $entity->get('id');
                    } elseif ($entityType === $foreignEntity->getEntityType()) {
                        $input[$field . 'Id'] = $foreignEntity->get('id');
                    }
                }
                try {
                    $this->createEntity(json_decode(json_encode($input)));
                    $related++;
                } catch (\Throwable $e) {
                    $notRelated[] = [
                        'id'          => $entity->get('id'),
                        'name'        => $entity->get('name'),
                        'foreignId'   => $foreignEntity->get('id'),
                        'foreignName' => $foreignEntity->get('name'),
                        'message'     => utf8_encode($e->getMessage())
                    ];
                }
            }
        }

        return [
            'related'    => $related,
            'notRelated' => $notRelated
        ];
    }

    public function deleteRelationshipEntitiesViaRemoveRelation(string $entityType, EntityCollection $entities, array $foreignIds): array
    {
        $relationshipEntities = $this->getRelationshipEntities();
        if (count($relationshipEntities) !== 2) {
            throw new BadRequest('Action blocked.');
        }

        $foreignCollection = new EntityCollection();
        foreach ($relationshipEntities as $relationshipEntity) {
            if ($relationshipEntity !== $entityType) {
                $foreignCollection = $this->getEntityManager()->getRepository($relationshipEntity)->where(['id' => $foreignIds])->find();
            }
        }

        $unRelated = 0;
        $notUnRelated = [];

        foreach ($entities as $entity) {
            foreach ($foreignCollection as $foreignEntity) {
                $where = [];
                foreach ($relationshipEntities as $field => $entityType) {
                    if ($entityType === $entity->getEntityType()) {
                        $where[$field . 'Id'] = $entity->get('id');
                    } elseif ($entityType === $foreignEntity->getEntityType()) {
                        $where[$field . 'Id'] = $foreignEntity->get('id');
                    }
                }
                try {
                    $record = $this->getRepository()->where($where)->findOne();
                    if (!empty($record)) {
                        $this->getEntityManager()->removeEntity($record);
                    }
                    $unRelated++;
                } catch (\Throwable $e) {
                    $notUnRelated[] = [
                        'id'          => $entity->get('id'),
                        'name'        => $entity->get('name'),
                        'foreignId'   => $foreignEntity->get('id'),
                        'foreignName' => $foreignEntity->get('name'),
                        'message'     => utf8_encode($e->getMessage())
                    ];
                }
            }
        }

        return [
            'unRelated'    => $unRelated,
            'notUnRelated' => $notUnRelated
        ];
    }

    protected function getRelationshipEntities(): array
    {
        $relationshipEntities = [];
        foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields']) as $field => $fieldDefs) {
            if (!empty($fieldDefs['relationshipField'])) {
                $relationshipEntities[$field] = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links', $field, 'entity']);
            }
        }

        return $relationshipEntities;
    }

    protected function storeEntity(Entity $entity)
    {
        try {
            $result = $this->getRepository()->save($entity, $this->getDefaultRepositoryOptions());
        } catch (\PDOException $e) {
            if (!empty($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                return true;
            }
            throw $e;
        }

        return $result;
    }
}
