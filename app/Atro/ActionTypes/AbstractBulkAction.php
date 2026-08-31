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

namespace Atro\ActionTypes;

use Atro\Core\Exceptions\BadRequest;
use Atro\Entities\ActionExecution;
use Espo\ORM\Entity;

/**
 * Base for action types that either act on one preselected record (applyToPreselectedRecords)
 * or on every record matching the action's filter. Filter-driven runs that match more than
 * massUpdateMaxCountWithoutJob records are handed off to the generic Atro\Jobs\MassActionCreator,
 * which splits the matched set into per-chunk Atro\Jobs\ActionHandler jobs - the same chunking
 * mechanism used for mass-updates, reused here instead of reimplemented.
 */
abstract class AbstractBulkAction extends AbstractAction
{
    /**
     * Perform the actual per-record operation. May throw - runForEntity() logs it and continues.
     * $log is the ActionExecutionLog being built for this entity - set its 'type'/'message'
     * to override the default outcome-based values runForEntity() would otherwise apply.
     */
    abstract protected function processEntity(Entity $entity, Entity $log): bool;

    public function useMassActions(Entity $action, \stdClass $input): bool
    {
        return !empty($action->get('applyToPreselectedRecords'));
    }

    public function execute(ActionExecution $execution, \stdClass $input): bool
    {
        $action       = $execution->get('action');
        $sourceEntity = $this->getSourceEntity($action, $input);

        if (empty($action->get('applyToPreselectedRecords'))) {
            $entityType = $action->get('searchEntity');
            $where      = $this->getWhere($action) ?? [];

            $selectManager = $this->container->get('selectManagerFactory')->create($entityType);
            $repository    = $this->getEntityManager()->getRepository($entityType);
            $selectParams  = $selectManager->getSelectParams(['where' => $where], true, true);
            $repository->handleSelectParams($selectParams);
            $count = $repository->count($selectParams);

            if ($count === 0) {
                $execution->set('status', 'done');
                $this->getEntityManager()->saveEntity($execution);

                return true;
            }

            if ($count > $this->getConfig()->get('massUpdateMaxCountWithoutJob', 200)) {
                $this->dispatchChunkCreatorJob($action, $execution, $entityType, $where, $count);

                return true;
            }

            $collection = $repository->find($selectParams);

            foreach ($collection as $entity) {
                $this->runForEntity($entity, $execution);
            }

            $execution->set('status', 'done');
            $this->getEntityManager()->saveEntity($execution);

            return true;
        }

        if (empty($sourceEntity)) {
            throw new BadRequest('Action can be executed only from Source Entity.');
        }

        $result = $this->runForEntity($sourceEntity, $execution);

        $execution->set('status', 'done');
        $this->getEntityManager()->saveEntity($execution);

        return $result;
    }

    /**
     * Process one entity against a (possibly shared, across a chunk) ActionExecution, logging
     * the outcome via ActionExecutionLog. Public so Atro\Jobs\ActionHandler can call it directly
     * for each id in a chunk, reusing one execution/its logs instead of creating a fresh
     * execution per record.
     */
    public function runForEntity(Entity $entity, ActionExecution $execution): bool
    {
        $log = $this->getEntityManager()->getRepository('ActionExecutionLog')->get();
        $log->set([
            'actionExecutionId' => $execution->get('id'),
            'entityName'        => $entity->getEntityName(),
            'entityId'          => $entity->get('id'),
        ]);

        try {
            $result = $this->processEntity($entity, $log);
            if (empty($log->get('type'))) {
                $log->set('type', $result ? 'update' : 'error');
            }
            if (!$result && empty($log->get('message'))) {
                $log->set('message', 'Action was not applicable to this record.');
            }
        } catch (\Throwable $e) {
            $result = false;
            if (empty($log->get('type'))) {
                $log->set('type', 'error');
            }
            if (empty($log->get('message'))) {
                $log->set('message', $e->getMessage());
            }
        }

        $this->getEntityManager()->saveEntity($log);

        return $result;
    }

    protected function dispatchChunkCreatorJob(Entity $action, ActionExecution $execution, string $entityType, array $where, int $total): void
    {
        $maxConcurrentJobs = $this->getConfig()->get('maxConcurrentJobs', 6);
        $maxChunkSize      = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);
        $minChunkSize      = $this->getConfig()->get('massUpdateMinChunkSize', 400);
        $chunkSize         = \Atro\Services\Record::getChunkSize($total, $maxChunkSize, $minChunkSize, $maxConcurrentJobs);

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'    => 'Create chunk jobs: ' . $action->get('name'),
            'type'    => 'MassActionCreator',
            'payload' => [
                'entityName' => $entityType,
                'action'     => 'action',
                'total'      => $total,
                'chunkSize'  => $chunkSize,
                'params'     => [
                    'where'             => $where,
                    'additionalJobData' => [
                        'actionId'          => $action->get('id'),
                        'actionExecutionId' => $execution->get('id'),
                        'sourceEntity'      => $action->get('sourceEntity'),
                    ],
                ],
            ],
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);
    }
}
