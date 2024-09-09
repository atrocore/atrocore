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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\EventManager\Event;
use Atro\Core\QueueManager;
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

            $this->getQueueManager()->createQueueItem(
                "Create jobs for mass $action",
                'MassActionCreator',
                [
                    'ids'        => $ids ?? [],
                    'action'     => $action,
                    'entityName' => $this->entityType,
                    'chunkSize'  => (int)$chunkSize,
                    'total'      => $total,
                    'params'     => $params,
                ]
            );
        }

        if (!empty($errors)) {
            $label = "mass" . ucfirst($action);
            $label .= count($errors) === count($ids) ? "NoRecordProceed" : "SomeRecordNotProceed";
            array_unshift($errors, $this->getInjection('language')->translate($label, 'exceptions'));
        }

        return [$total, $errors, $sync];
    }

    protected function getQueueManager(): QueueManager
    {
        return $this->getInjection('queueManager');
    }
}
