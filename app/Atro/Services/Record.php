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

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\NotUnique;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Services\RecordService;

class Record extends RecordService
{
    /**
     * @param array $params
     *
     * @return array
     */
    public function massRemove(array $params)
    {
        $params = $this
            ->dispatchEvent('beforeMassDelete', new Event(['params' => $params, 'service' => $this]))
            ->getArgument('params');

        $params['action'] = 'delete';
        $params['maxCountWithoutJob'] = $this->getConfig()->get('massDeleteMaxCountWithoutJob', 200);
        $params['maxChunkSize'] = $this->getConfig()->get('massDeleteMaxChunkSize', 3000);
        $params['minChunkSize'] = $this->getConfig()->get('massDeleteMinChunkSize', 400);

        if (!empty($params['permanently'])) {
            $callback = function ($id) {
                $this->deleteEntityPermanently($id);
            };
        } else {
            $callback = function ($id) {
                $this->deleteEntity($id);
            };
        }

        list($count, $errors, $sync) = $this->executeMassAction($params, $callback);

        return $this
            ->dispatchEvent('afterMassDelete',
                new Event(['service' => $this, 'result' => ['count' => $count, 'sync' => $sync, 'errors' => $errors]]))
            ->getArgument('result');
    }

    public function deleteEntityPermanently(string $id): bool
    {
        try {
            $deleted = $this->deleteEntity($id);
        } catch (NotFound $e) {
            if (empty($this->getRepository()->markedAsDeleted($id))) {
                throw new NotFound();
            }
            $deleted = true;
        }

        $res = false;

        if ($deleted) {
            $id = $this
                ->dispatchEvent('beforeDeleteEntityPermanently', new Event(['id' => $id, 'service' => $this]))
                ->getArgument('id');
            if (!empty($id)) {
                $this->getRepository()->deleteFromDb($id);
            }

            $res = true;
        }

        return $this
            ->dispatchEvent('afterDeleteEntityPermanently', new Event(['id' => $id, 'service' => $this, 'res' => $res]))
            ->getArgument('res');
    }

    protected function executeMassAction(array $params, \Closure $actionOperation): array
    {
        if (empty($params['action']) || empty($params['maxCountWithoutJob']) || empty($params['maxChunkSize']) || empty($params['minChunkSize'])) {
            return [];
        }

        $action = $params['action'];
        $maxCountWithoutJob = $params['maxCountWithoutJob'];
        $maxChunkSize = $params['maxChunkSize'];
        $minChunkSize = $params['minChunkSize'];
        $maxConcurrentJobs = $this->getConfig()->get('maxConcurrentJobs', 6);

        if (!in_array($action, ['restore', 'delete', 'update'])) {
            return [];
        }

        $repository = $this->getEntityManager()->getRepository($this->entityType);
        $byWhere = array_key_exists('where', $params);
        $errors = [];

        if (array_key_exists('ids', $params) && !empty($params['ids']) && is_array($params['ids'])) {
            $ids = $params['ids'];
            $total = count($ids);
        } elseif ($byWhere) {
            $selectParams = $this->getSelectParams(['where' => $params['where']], true, true);
            if ($action === 'delete' && !empty($params['permanently'])) {
                $selectParams['withDeleted'] = true;
            }

            $repository->handleSelectParams($selectParams);
            $total = $repository->count(array_merge($selectParams, ['select' => ['id']]));
        } else {
            $ids = [];
            $total = 0;
        }

        $sync = true;

        if ($total <= $maxCountWithoutJob) {
            if ($byWhere) {
                $collection = $repository->find(array_merge($selectParams, ['select' => ['id']]));
                $ids = array_column($collection->toArray(), 'id');
            }
            foreach ($ids as $id) {
                try {
                    $actionOperation($id);
                } catch (\Throwable $e) {
                    $message = "{$action} {$this->getEntityType()} '$id' failed: {$e->getTraceAsString()}";
                    $GLOBALS['log']->error($message);
                    $entity = $this->getRepository()->get($id);
                    $name = !empty($entity) ? $entity->get('name') : $id;
                    $errors[] = "Error for '$name': {$e->getMessage()}";
                }
            }
        } else {
            if ($total <= ($minChunkSize * $maxConcurrentJobs)) {
                $chunkSize = $minChunkSize;
            } else {
                if ($total >= ($minChunkSize * $maxConcurrentJobs) && $total <= ($maxChunkSize * $maxConcurrentJobs)) {
                    $chunkSize = ceil($total / $maxConcurrentJobs);
                } else {
                    $chunkSize = $maxChunkSize;
                }
            }

            $sync = false;

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'     => "Create jobs for mass $action",
                'type'     => 'MassActionCreator',
                'priority' => $this->entityType === 'Job' ? 300 : 100,
                'payload'  => [
                    'ids'        => $ids ?? [],
                    'action'     => $action,
                    'entityName' => $this->entityType,
                    'chunkSize'  => (int)$chunkSize,
                    'total'      => $total,
                    'params'     => $params,
                ]
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);
        }

        if (!empty($errors)) {
            $label = "mass" . ucfirst($action);
            $label .= count($errors) === count($ids) ? "NoRecordProceed" : "SomeRecordNotProceed";
            array_unshift($errors, $this->getInjection('language')->translate($label, 'exceptions'));
        }

        return [$total, $errors, $sync];
    }

    public function merge($id, array $sourceIdList, \stdClass $attributes)
    {
        if (empty($id)) {
            throw new Error();
        }

        $repository = $this->getRepository();

        $entity = $this->getEntityManager()->getEntity($this->getEntityType(), $id);

        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $input = $attributes->input;
        $relationshipData = json_decode(json_encode($attributes->relationshipData), true);

        $this->filterInput($input);

        $entity->set($input);
        if (!$this->checkAssignment($entity)) {
            throw new Forbidden();
        }

        $sourceList = array();
        foreach ($sourceIdList as $sourceId) {
            $source = $this->getEntity($sourceId);
            $sourceList[] = $source;
            if (!$this->getAcl()->check($source, 'edit') || !$this->getAcl()->check($source, 'delete')) {
                throw new Forbidden();
            }
        }

        $this->beforeMerge($entity, $sourceList, $attributes);

        $connection = $this->getEntityManager()->getConnection();

        $types = ['Post', 'EmailSent', 'EmailReceived'];

        foreach ($sourceList as $source) {
            $connection->createQueryBuilder()
                ->update($connection->quoteIdentifier('note'), 'n')
                ->set('parent_id', ':entityId')
                ->set('parent_type', ':entityType')
                ->where('n.type IN (:types)')
                ->andWhere('n.parent_id = :sourceId')
                ->andWhere('n.parent_type = :sourceType')
                ->andWhere('n.deleted = :false')
                ->setParameter('entityId', $entity->id)
                ->setParameter('entityType', $entity->getEntityType())
                ->setParameter('types', $types, Mapper::getParameterType($types))
                ->setParameter('sourceId', $source->id)
                ->setParameter('sourceType', $source->getEntityType())
                ->setParameter('false', false, Mapper::getParameterType(false))
                ->executeQuery();
        }

        $mergeLinkList = [];
        $linksDefs = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links']);
        $customMergeLinkList = array_keys($relationshipData);
        $customEntityToMerge = array_column( array_values($relationshipData), 'scope');
        foreach ($linksDefs as $link => $d) {
            if (!empty($d['notMergeable'])) {
                continue;
            }

            if(in_array($link, $customMergeLinkList)) {
                continue;
            }


            if (!empty($d['type']) && in_array($d['type'], ['hasMany', 'hasChildren'])) {
                if(empty($d['entity']) || empty($d['foreign'])) {
                    continue;
                }

                if(in_array($d['entity'], $customEntityToMerge)){
                    continue;
                }

                $mergeLinkList[] = $link;
            }
        }


        foreach ($sourceList as $source) {
            foreach ($mergeLinkList as $link) {
                $linkedList = $repository->findRelated($source, $link);
                foreach ($linkedList as $linked) {
                    try {
                        $repository->relate($entity, $link, $linked);
                    }catch (NotUnique $e) {
                        $test = $e;
                    }
                }
            }
        }

        $upsertData = [];
        foreach ($relationshipData as $key => $data) {
            if(empty($data['scope'])) {
                continue;
            }
            if(!empty($data['toUpsert'])) {
                foreach ($data['toUpsert'] as $payload) {
                    $input = new \stdClass();
                    $input->entity = $data['scope'];
                    $input->payload = (object)$payload;
                    $upsertData[] = $input;
                }
            }

            if(!empty($data['toDelete'])) {
                $this->getRecordService($data['scope'])->massRemove([
                    'ids' => $data['toDelete']
                ]);
            }
        }

        $this->getRecordService('MassActions')->upsert($upsertData);


        foreach ($sourceList as $source) {
            $this->getEntityManager()->removeEntity($source);
        }

        $entity->set($attributes);
        $repository->save($entity);

        $this->afterMerge($entity, $sourceList, $attributes);

        return true;
    }

}
