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

namespace Treo\Services;

use Espo\Core\Exceptions\BadRequest;

/**
 * Class MassActions
 */
class MassActions extends AbstractService
{
    /**
     * @param string    $entityType
     * @param \stdClass $data
     *
     * @return array
     */
    public function massUpdate(string $entityType, \stdClass $data): array
    {
        // get ids
        $ids = $this->getMassActionIds($entityType, $data);

        // attributes
        $attributes = $data->attributes;

        if (count($ids) > $this->getWebMassUpdateMax()) {
            // create jobs
            $this->createMassUpdateJobs($entityType, $attributes, $ids);

            return [
                'count'          => 0,
                'ids'            => [],
                'byQueueManager' => true
            ];
        }

        return $this->getService($entityType)->massUpdate($attributes, ['ids' => $ids]);
    }

    /**
     * @param string    $entityType
     * @param \stdClass $data
     *
     * @return array
     */
    public function massDelete(string $entityType, \stdClass $data): array
    {
        // get ids
        $ids = $this->getMassActionIds($entityType, $data);

        if (count($ids) > $this->getWebMassUpdateMax()) {
            // create jobs
            $this->createMassDeleteJobs($entityType, $ids);

            return [
                'count'          => 0,
                'ids'            => [],
                'byQueueManager' => true
            ];
        }

        return $this->getService($entityType)->massRemove(['ids' => $ids]);
    }

    /**
     * Add relation to entities
     *
     * @param array  $ids
     * @param array  $foreignIds
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
                    } catch (BadRequest $e) {
                        $related--;
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
        }

        return ['message' => $this->createRelationMessage($related, $notRelated, $entityType, $foreignEntityType)];
    }

    /**
     * Remove relation from entities
     *
     * @param array  $ids
     * @param array  $foreignIds
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
                            'id'          => $entity->get('id'),
                            'name'        => $entity->get('name'),
                            'foreignId'   => $foreignEntity->get('id'),
                            'foreignName' => $foreignEntity->get('name'),
                            'message'     => utf8_encode($e->getMessage())
                        ];
                    }
                }
            }
        }

        return ['message' => $this->createRelationMessage($unRelated, $notUnRelated, $entityType, $foreignEntityType, false)];
    }

    /**
     * @param int    $success
     * @param array  $errors
     * @param string $entityType
     * @param string $foreignEntityType
     * @param bool   $relate
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
     * @param string    $entityType
     * @param \stdClass $data
     *
     * @return array
     */
    protected function getMassActionIds(string $entityType, \stdClass $data): array
    {
        $res = $this
            ->getEntityManager()
            ->getRepository($entityType)
            ->select(['id'])
            ->find($this->getSelectParams($entityType, $this->getWhere($data)));

        return (empty($res)) ? [] : array_column($res->toArray(), 'id');
    }

    /**
     * @param string    $entityType
     * @param \stdClass $attributes
     * @param array     $ids
     */
    protected function createMassUpdateJobs(string $entityType, \stdClass $attributes, array $ids): void
    {
        if (count($ids) > $this->getCronMassUpdateMax()) {
            foreach ($this->getParts($ids) as $part => $rows) {
                // prepare data
                $name = $entityType . ". " . sprintf($this->translate('massUpdatePartial', 'massActions'), $part);
                $data = [
                    'entityType' => $entityType,
                    'attributes' => $attributes,
                    'ids'        => $rows
                ];

                // push
                $this->qmPush($name, "QueueManagerMassUpdate", $data, true);
            }
        } else {
            // prepare data
            $name = $entityType . ". " . $this->translate('massUpdate', 'massActions');
            $data = [
                'entityType' => $entityType,
                'attributes' => $attributes,
                'ids'        => $ids
            ];

            // push
            $this->qmPush($name, "QueueManagerMassUpdate", $data, true);
        }
    }

    /**
     * @param string $entityType
     * @param array  $ids
     */
    protected function createMassDeleteJobs(string $entityType, array $ids): void
    {
        if (count($ids) > $this->getCronMassUpdateMax()) {
            foreach ($this->getParts($ids) as $part => $rows) {
                // prepare data
                $name = $entityType . ". " . sprintf($this->translate('removePartial', 'massActions'), $part);
                $data = [
                    'entityType' => $entityType,
                    'ids'        => $rows
                ];

                // push
                $this->qmPush($name, "QueueManagerMassDelete", $data);
            }
        } else {
            // prepare data
            $name = $entityType . ". " . $this->translate('remove', 'massActions');
            $data = [
                'entityType' => $entityType,
                'ids'        => $ids
            ];

            // push
            $this->qmPush($name, "QueueManagerMassDelete", $data);
        }
    }

    /**
     * @param array $ids
     *
     * @return array
     */
    protected function getParts(array $ids): array
    {
        // prepare vars
        $result = [];
        $part = 1;
        $tmpIds = [];

        foreach ($ids as $id) {
            if (count($tmpIds) == $this->getCronMassUpdateMax()) {
                $result[$part] = $tmpIds;

                // clearing tmp ids
                $tmpIds = [];

                // increase parts
                $part++;
            }

            // push to tmp ids
            $tmpIds[] = $id;
        }

        if (!empty($tmpIds)) {
            $result[$part] = $tmpIds;
        }

        return $result;
    }

    /**
     * @param \stdClass $data
     *
     * @return array
     */
    protected function getWhere(\stdClass $data): array
    {
        // prepare where
        $where = [];
        if (property_exists($data, 'where') && !empty($data->byWhere)) {
            $where = json_decode(json_encode($data->where), true);
        } else {
            if (property_exists($data, 'ids')) {
                $values = [];
                foreach ($data->ids as $id) {
                    $values[] = [
                        'type'      => 'equals',
                        'attribute' => 'id',
                        'value'     => $id
                    ];
                }
                $where[] = [
                    'type'  => 'or',
                    'value' => $values
                ];
            }
        }

        return $where;
    }

    /**
     * @param string $entityType
     * @param array  $where
     *
     * @return array
     */
    protected function getSelectParams(string $entityType, array $where): array
    {
        return $this
            ->getContainer()
            ->get('selectManagerFactory')
            ->create($entityType)
            ->getSelectParams(['where' => $where], true, true);
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
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function getForeignEntityType(string $entityType, string $link): string
    {
        return $this
            ->getEntityManager()
            ->getEntity($entityType)
            ->getRelationParam($link, 'entity');
    }

    /**
     * @return int
     */
    protected function getWebMassUpdateMax(): int
    {
        return (int)$this->getConfig()->get('webMassUpdateMax', 200);
    }

    /**
     * @param string $name
     * @param string $serviceName
     * @param array  $data
     * @param bool   $isWriting
     *
     * @return bool
     */
    private function qmPush(string $name, string $serviceName, array $data, bool $isWriting = false): bool
    {
        return $this
            ->getContainer()
            ->get('queueManager')
            ->push($name, $serviceName, $data, $isWriting);
    }

    /**
     * @return int
     */
    private function getCronMassUpdateMax(): int
    {
        return (int)$this->getConfig()->get('cronMassUpdateMax', 2000);
    }
}
