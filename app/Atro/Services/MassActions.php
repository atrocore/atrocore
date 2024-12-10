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

namespace Atro\Services;

use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Services\HasContainer;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;

class MassActions extends HasContainer
{
    protected function init()
    {
        $this->addDependency('selectManagerFactory');
        parent::init();
    }

    public function upsertViaJob(array $data): array
    {
        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'    => 'Action Upsert',
            'type'    => 'Upsert',
            'payload' => $data
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);

        return [
            "jobId" => $jobEntity->get('id')
        ];
    }

    public function upsert(array $data): array
    {
        $result = [];
        foreach ($data as $k => $node) {
            if (!property_exists($node, 'entity')) {
                $result[$k] = [
                    'status'  => 'Failed',
                    'stored'  => false,
                    'message' => "'entity' parameter is required."
                ];
                continue 1;
            }

            if (!property_exists($node, 'payload')) {
                $result[$k] = [
                    'status'  => 'Failed',
                    'stored'  => false,
                    'message' => "'payload' parameter is required."
                ];
                continue 1;
            }

            try {
                $service = $this->getContainer()->get('serviceFactory')->create($node->entity);
            } catch (\Throwable $e) {
                $result[$k] = [
                    'status'  => 'Failed',
                    'stored'  => false,
                    'message' => $e->getMessage()
                ];
                continue 1;
            }

            $existed = null;
            if (property_exists($node->payload, 'id')) {
                $existed = $this->getEntityManager()->getEntity($node->entity, $node->payload->id);
            }
            if (empty($existed)) {
                // Check if entity exists
                $fields = $this->getMetadata()->get(['entityDefs', $node->entity, 'fields']);
                $uniqueFields = array_filter($fields, function ($field) {
                    return isset($field['unique']) && $field['unique'] == true;
                });

                $uniqueIndexes = [];
                if (empty($uniqueFields)) {
                    foreach ($this->getMetadata()->get(['entityDefs', $node->entity, 'uniqueIndexes']) as $indexes) {
                        $uniqueIndexes[] = array_map(fn($index) => Util::toCamelCase($index), array_diff($indexes, ['deleted']));
                    }
                }

                $whereClause = [];
                if (count($uniqueFields) > 0) {
                    foreach ($uniqueFields as $key => $field) {
                        if (!empty($node->payload->{$key})) {
                            $whereClause[] = [$key => $node->payload->{$key}];
                        }
                    }
                } else if (count($uniqueIndexes) > 0) {
                    foreach ($uniqueIndexes as $indexes) {
                        if (array_reduce($indexes, fn($carry, $index) => $carry && !empty($node->payload->{$index}), true)) {
                            foreach ($indexes as $index) {
                                $whereClause[] = [$index => $node->payload->{$index}];
                            }
                        }
                    }
                }

                if (count($whereClause) > 0) {
                    $existed = $this->getEntityManager()
                        ->getRepository($node->entity)
                        ->where($whereClause)
                        ->findOne();
                }
            }

            if (!empty($existed)) {
                try {
                    $updated = $service->updateEntity($existed->get('id'), $node->payload);
                    $result[$k] = [
                        'status' => 'Updated',
                        'stored' => true,
                        'entity' => $updated->toArray()
                    ];
                } catch (\Throwable $e) {
                    $result[$k] = [
                        'status'  => 'Failed',
                        'stored'  => false,
                        'message' => 'Code: ' . $e->getCode() . '. Message: ' . $e->getMessage()
                    ];
                }
                continue;
            }

            try {
                $created = $service->createEntity($node->payload);
                $result[$k] = [
                    'status' => 'Created',
                    'stored' => true,
                    'entity' => $created->toArray()
                ];
            } catch (\Throwable $e) {
                $result[$k] = [
                    'status'  => 'Failed',
                    'stored'  => false,
                    'message' => 'Code: ' . $e->getCode() . '. Message: ' . $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Add relation to entities
     *
     * @param array $ids
     * @param array $foreignIds
     * @param string $entityType
     * @param string $link
     *
     * @return array
     */
    public function addRelation(array $ids, array $foreignIds, string $entityType, string $link): array
    {
        // prepare service
        $service = $this->getService($entityType);
        $methodName = 'massRelate' . ucfirst($link);

        // if method exists
        if (method_exists($service, $methodName)) {
            return $service->$methodName($ids, $foreignIds);
        }

        // prepare repository
        $repository = $this->getRepository($entityType);

        // find entities
        $entities = $repository->where(['id' => $ids])->find();

        /** @var string $foreignEntityType */
        $foreignEntityType = $this->getForeignEntityType($entityType, $link);

        if ($this->getMetadata()->get(['scopes', $foreignEntityType, 'type']) === 'Relationship') {
            $result = $this->getService($foreignEntityType)->createRelationshipEntitiesViaAddRelation($entityType, $entities, $foreignIds);
            return ['message' => $this->createRelationMessage($result['related'], $result['notRelated'], $entityType, $foreignEntityType)];
        }

        // find foreign entities
        $foreignEntities = $this
            ->getRepository($foreignEntityType)
            ->where(['id' => $foreignIds])
            ->find();

        $related = 0;
        $notRelated = [];
        if ($entities->count() > 0 && $foreignEntities->count() > 0) {
            foreach ($entities as $entity) {
                foreach ($foreignEntities as $foreignEntity) {
                    $related++;
                    try {
                        $repository->relate($entity, $link, $foreignEntity);
                    } catch (UniqueConstraintViolationException $e) {
                    } catch (NotUnique $e) {
                        $message = $this->getLanguage()->translate('notUniqueCreatedValue', 'exceptions');
                        throw new NotUnique($message);
                    } catch (BadRequest $e) {
                        $related--;
                        $notRelated[] = [
                            'id' => $entity->get('id'),
                            'name' => $entity->get('name'),
                            'foreignId' => $foreignEntity->get('id'),
                            'foreignName' => $foreignEntity->get('name'),
                            'message' => utf8_encode($e->getMessage())
                        ];
                    }
                }
            }
        }

        return ['message' => $this->createRelationMessage($related, $notRelated, $entityType, $foreignEntityType)];
    }

    /**
     * Remove relation from entities
     *
     * @param array $ids
     * @param array $foreignIds
     * @param string $entityType
     * @param string $link
     *
     * @return array
     */
    public function removeRelation(array $ids, array $foreignIds, string $entityType, string $link): array
    {
        // prepare service
        $service = $this->getService($entityType);
        $methodName = 'massUnrelate' . ucfirst($link);

        // if method exists
        if (method_exists($service, $methodName)) {
            return $service->$methodName($ids, $foreignIds);
        }

        // prepare repository
        $repository = $this->getRepository($entityType);

        // find entities
        $entities = $repository->where(['id' => $ids])->find();

        /** @var string $foreignEntityType */
        $foreignEntityType = $this->getForeignEntityType($entityType, $link);

        if ($this->getMetadata()->get(['scopes', $foreignEntityType, 'type']) === 'Relationship') {
            $result = $this->getService($foreignEntityType)->deleteRelationshipEntitiesViaRemoveRelation($entityType, $entities, $foreignIds);
            return ['message' => $this->createRelationMessage($result['unRelated'], $result['notUnRelated'], $entityType, $foreignEntityType, false)];
        }

        // find foreign entities
        $foreignEntities = $this
            ->getRepository($foreignEntityType)
            ->where(['id' => $foreignIds])
            ->find();

        $unRelated = 0;
        $notUnRelated = [];
        if ($entities->count() > 0 && $foreignEntities->count() > 0) {
            foreach ($entities as $entity) {
                foreach ($foreignEntities as $foreignEntity) {
                    $unRelated++;
                    try {
                        $repository->unrelate($entity, $link, $foreignEntity);
                    } catch (BadRequest $e) {
                        $unRelated--;
                        $notUnRelated[] = [
                            'id' => $entity->get('id'),
                            'name' => $entity->get('name'),
                            'foreignId' => $foreignEntity->get('id'),
                            'foreignName' => $foreignEntity->get('name'),
                            'message' => utf8_encode($e->getMessage())
                        ];
                    }
                }
            }
        }

        return ['message' => $this->createRelationMessage($unRelated, $notUnRelated, $entityType, $foreignEntityType, false)];
    }

    public function addRelationByWhere(array $where, array $foreignWhere, string $entityType, string $link): array
    {
        $ids = $this->handleIdsFromWhereCondition($entityType, $where);

        $foreignEntityType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'addRelationCustomDefs', 'entity']);
        if (empty($foreignEntityType)) {
            $foreignEntityType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);
        }

        $foreignIds = $this->handleIdsFromWhereCondition($foreignEntityType, $foreignWhere);

        return $this->addRelation($ids, $foreignIds, $entityType, $link);
    }

    public function removeRelationByWhere(array $where, array $foreignWhere, string $entityType, string $link): array
    {
        $ids = $this->handleIdsFromWhereCondition($entityType, $where);

        $foreignEntityType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'addRelationCustomDefs', 'entity']);
        if (empty($foreignEntityType)) {
            $foreignEntityType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);
        }

        $foreignIds = $this->handleIdsFromWhereCondition($foreignEntityType, $foreignWhere);

        return $this->removeRelation($ids, $foreignIds, $entityType, $link);
    }

    /**
     * @param array $entityType
     * @param array $where
     *
     * @return array
     */
    protected function handleIdsFromWhereCondition(string $entityType, array $where): array
    {
        $selectParams = $this->getSelectManagerFactory()->create($entityType)->getSelectParams(['where' => $where]);
        $this->getEntityManager()->getRepository($entityType)->handleSelectParams($selectParams);
        $collection = $this->getEntityManager()->getRepository($entityType)->find(array_merge($selectParams, ['select' => ['id']]));

        return array_column($collection->toArray(), 'id');
    }


    protected function getSelectManagerFactory()
    {
        return $this->getContainer()->get('selectManagerFactory');
    }


    /**
     * @param int $success
     * @param array $errors
     * @param string $entityType
     * @param string $foreignEntityType
     * @param bool $relate
     *
     * @return string
     */
    public function createRelationMessage(int $success, array $errors, string $entityType, string $foreignEntityType, bool $relate = true): string
    {
        $message = "<button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>";
        if (!empty($success)) {
            $plural = $success > 1 ? 'Plural' : '';
            $successMessage = $relate ? $this->translate('relationsAdded' . $plural, 'messages') : $this->translate('relationsRemoved' . $plural, 'messages');
            $message .= "<span>" . sprintf($successMessage, $success) . "</span><br>";
        }
        if (!empty($errors)) {
            $plural = count($errors) > 1 ? 'Plural' : '';
            $errorMessage = $relate ? $this->translate('relationsDidNotAdded' . $plural, 'messages') : $this->translate('relationsDidNotRemoved' . $plural, 'messages');
            $message .= "<span style=\"color: red\">" . sprintf($errorMessage, count($errors)) . "</span><br>";
            foreach ($errors as $item) {
                $message .= "<span style=\"margin-left: 10px; color: #000\"><a target=\"_blank\" href=\"#{$entityType}/view/{$item['id']}\">{$item['name']}</a> &#8594; <a target=\"_blank\" href=\"#{$foreignEntityType}/view/{$item['foreignId']}\">{$item['foreignName']}</a>: {$item['message']}</span><br>";
            }
        }

        return $message;
    }

    /**
     * Get repository
     *
     * @param string $entityType
     *
     * @return mixed
     */
    protected function getRepository(string $entityType)
    {
        return $this->getEntityManager()->getRepository($entityType);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    protected function getService(string $name)
    {
        return $this
            ->getContainer()
            ->get('serviceFactory')
            ->create($name);
    }

    /**
     * @param string $entityType
     * @param string $link
     *
     * @return string
     * @throws \Atro\Core\Exceptions\Error
     */
    protected function getForeignEntityType(string $entityType, string $link): string
    {
        $foreignEntityType = $this->getEntityManager()->getEntity($entityType)->getRelationParam($link, 'entity');

        if (empty($foreignEntityType)) {
            throw new BadRequest("No such relation found.");
        }

        return $foreignEntityType;
    }

    /**
     * @param string $name
     * @param string $serviceName
     * @param array $data
     *
     * @return bool
     */
    private function qmPush(string $name, string $serviceName, array $data): bool
    {
        return $this
            ->getContainer()
            ->get('queueManager')
            ->push($name, $serviceName, $data);
    }

    private function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    private function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }
}