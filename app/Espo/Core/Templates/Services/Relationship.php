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
            if (!empty($relEntities = $this->getRelationEntities($collection))) {
                foreach ($collection as $entity) {
                    $this->prepareRelationVirtualFields($entity, $relEntities);
                }
            }
        }

        parent::prepareCollectionForOutput($collection, $selectParams);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        if ($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'relationsVirtualFields']) !== false && empty($entity->preparedRelationVirtualFields)) {
            if (!empty($relEntities = $this->getRelationEntities(new EntityCollection([$entity], $entity->getEntityName())))) {
                $this->prepareRelationVirtualFields($entity, $relEntities);
            }
        }

        parent::prepareEntityForOutput($entity);
    }

    public function getRelationEntities(EntityCollection $collection): array
    {
        $relEntities = [];

        foreach ($this->getMetadata()->get(['entityDefs', $collection->getEntityName(), 'fields'], []) as $field => $fieldDefs) {
            $parts = explode(self::VIRTUAL_FIELD_DELIMITER, $field);
            if (count($parts) === 2) {
                $relEntityName = $this->getMetadata()->get(['entityDefs', $collection->getEntityName(), 'links', $parts[0], 'entity']);
                if (!isset($relEntities[$parts[0]])) {
                    $res = $this
                        ->getServiceFactory()
                        ->create($relEntityName)
                        ->findEntities(['where' => [['type' => 'in', 'attribute' => 'id', 'value' => array_column($collection->toArray(), $parts[0] . 'Id')]]]);
                    if (isset($res['collection'])) {
                        foreach ($res['collection'] as $relEntity) {
                            $relEntities[$parts[0]][$relEntity->get('id')] = $relEntity;
                        }
                    }
                }
            }
        }

        return $relEntities;
    }

    public function prepareRelationVirtualFields(Entity $entity, array $relEntities): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityName(), 'fields'], []) as $field => $fieldDefs) {
            $parts = explode(self::VIRTUAL_FIELD_DELIMITER, $field);
            if (count($parts) === 2) {
                $relEntity = $relEntities[$parts[0]][$entity->get($parts[0] . 'Id')];

                $fieldType = $this->getMetadata()->get(['entityDefs', $relEntity->getEntityType(), 'fields', $parts[1], 'type']);

                switch ($fieldType) {
                    case 'currency':
                        $entity->set($field, $relEntity->get($parts[1]));
                        $entity->set($field . 'Currency', $relEntity->get($parts[1] . 'Currency'));
                        break;
                    case 'unit':
                        $entity->set($field, $relEntity->get($parts[1]));
                        $entity->set($field . 'Unit', $relEntity->get($parts[1] . 'Unit'));
                        break;
                    case 'link':
                    case 'file':
                    case 'asset':
                        $entity->set($field . 'Id', $relEntity->get($parts[1] . 'Id'));
                        $entity->set($field . 'Name', $relEntity->get($parts[1] . 'Name'));
                        break;
                    default:
                        $entity->set($field, $relEntity->get($parts[1]));
                }
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
