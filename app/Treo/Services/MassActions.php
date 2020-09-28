<?php

declare(strict_types=1);

namespace Treo\Services;

/**
 * Class MassActions
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
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
     * @return bool
     */
    public function addRelation(array $ids, array $foreignIds, string $entityType, string $link): bool
    {
        // prepare result
        $result = false;

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

        // find foreign entities
        $foreignEntities = $this
            ->getRepository($this->getForeignEntityType($entityType, $link))
            ->where(['id' => $foreignIds])
            ->find();

        if (count($entities) > 0 && count($foreignEntities) > 0) {
            foreach ($entities as $entity) {
                foreach ($foreignEntities as $foreignEntity) {
                    if ($repository->relate($entity, $link, $foreignEntity) && !$result) {
                        $result = true;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Remove relation from entities
     *
     * @param array  $ids
     * @param array  $foreignIds
     * @param string $entityType
     * @param string $link
     *
     * @return bool
     */
    public function removeRelation(array $ids, array $foreignIds, string $entityType, string $link): bool
    {
        // prepare result
        $result = false;

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

        // find foreign entities
        $foreignEntities = $this
            ->getRepository($this->getForeignEntityType($entityType, $link))
            ->where(['id' => $foreignIds])
            ->find();

        if (count($entities) > 0 && count($foreignEntities) > 0) {
            foreach ($entities as $entity) {
                foreach ($foreignEntities as $foreignEntity) {
                    if ($repository->unrelate($entity, $link, $foreignEntity) && !$result) {
                        $result = true;
                    }
                }
            }
        }

        return $result;
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
                $this->qmPush($name, "QueueManagerMassUpdate", $data);
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
            $this->qmPush($name, "QueueManagerMassUpdate", $data);
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

    /**
     * @return int
     */
    private function getCronMassUpdateMax(): int
    {
        return (int)$this->getConfig()->get('cronMassUpdateMax', 2000);
    }
}
