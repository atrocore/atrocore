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


class Record extends \Espo\Services\RecordService
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

        list($ids, $errors, $sync) = $this->executeMassAction($params, $callback);

        return $this
            ->dispatchEvent('afterMassDelete', new Event(['service' => $this, 'result' => ['count' => count($ids), 'ids' => $ids, 'sync' => $sync, 'errors' => $errors]]))
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
        $ids = [];
        $errors = [];

        if (empty($params['action']) || empty($params['maxCountWithoutJob']) || empty($params['maxChunkSize']) || empty($params['minChunkSize'])) {
            return [];
        }

        $action = $params['action'];
        $maxCountWithoutJob = $params['maxCountWithoutJob'];
        $maxChunkSize = $params['maxChunkSize'];
        $minChunkSize = $params['minChunkSize'];
        $additionJobData = !empty($params['additionalJobData']) ? $params['additionalJobData'] : [];
        $maxConcurrentJobs = $this->getConfig()->get('maxConcurrentJobs', 6);

        if (!in_array($action, ['restore', 'delete', 'update'])) {
            return [];
        }

        if (array_key_exists('ids', $params) && !empty($params['ids']) && is_array($params['ids'])) {
            $ids = $params['ids'];
        }

        if (array_key_exists('where', $params)) {
            $selectParams = $this->getSelectParams(['where' => $params['where']], true, true);
            if ($action === 'delete' && !empty($params['permanently'])) {
                $selectParams['withDeleted'] = true;
            }

            $repository = $this->getEntityManager()->getRepository($this->entityType);
            $repository->handleSelectParams($selectParams);

            $collection = $repository->find(array_merge($selectParams, ['select' => ['id']]));
            $ids = array_column($collection->toArray(), 'id');
        }

        $total = count($ids);

        $jobIds = [];
        if ($total <= $maxCountWithoutJob) {
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

            $chunks = array_chunk($ids, (int)$chunkSize);
            $totalChunks = count($chunks);

            if ($totalChunks > 1 && count($chunks[$totalChunks - 1]) < $minChunkSize) {
                $lastChunk = array_pop($chunks);
                $totalChunks = count($chunks);
                $chunks[$totalChunks - 1] = array_merge($chunks[$totalChunks - 1], $lastChunk);
            }

            foreach ($chunks as $part => $chunk) {
                $jobData = array_merge($additionJobData, [
                    'entityType'  => $this->getEntityType(),
                    'total'       => $total,
                    'chunkSize'   => count($chunk),
                    'totalChunks' => $totalChunks,
                    'ids'         => $chunk,
                ]);
                if ($action === 'delete' && !empty($params['permanently'])) {
                    $jobData['deletePermanently'] = true;
                }
                $name = $this->getInjection('language')->translate($action, 'massActions', 'Global') . ': ' . $this->entityName;
                if ($part > 0) {
                    $name .= " ($part)";
                }

                $jobIds[] = $this
                    ->getInjection('queueManager')
                    ->createQueueItem($name, 'Mass' . ucfirst($action), $jobData, 'Crucial');
            }
        }

        if (!empty($jobIds)) {
            QueueManagerBase::updatePublicData('mass' . ucfirst($action), $this->getEntityType(), [
                "jobIds" => $jobIds,
                "total"  => $total
            ]);
        }

        if (!empty($errors)) {
            $label = "mass" . ucfirst($action);
            $label .= count($errors) === count($ids) ? "NoRecordProceed" : "SomeRecordNotProceed";
            array_unshift($errors, $this->getInjection('language')->translate($label, 'exceptions'));
        }

        return [$ids, $errors, empty($jobIds)];
    }
}
