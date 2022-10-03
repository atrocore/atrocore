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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Espo\Core\Templates\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Services\Record;

class Relationship extends Record
{
    public function createRelationshipEntitiesViaAddRelation(string $entityType, EntityCollection $entities, array $foreignIds): array
    {
        $relationshipEntities = $this->getMetadata()->get(['scopes', $this->getEntityType(), 'relationshipEntities'], []);
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
                $input[lcfirst($entity->getEntityType()) . 'Id'] = $entity->get('id');
                $input[lcfirst($foreignEntity->getEntityType()) . 'Id'] = $foreignEntity->get('id');
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
        $relationshipEntities = $this->getMetadata()->get(['scopes', $this->getEntityType(), 'relationshipEntities'], []);
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
                try {
                    $record = $this
                        ->getRepository()
                        ->where([
                            lcfirst($entity->getEntityType()) . 'Id'        => $entity->get('id'),
                            lcfirst($foreignEntity->getEntityType()) . 'Id' => $foreignEntity->get('id'),
                        ])
                        ->findOne();
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
