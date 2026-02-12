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

namespace Atro\Core;

use Atro\ActionTypes\TypeInterface;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\KeyValueStorages\MemoryStorage;
use Atro\Core\Utils\Metadata;
use Espo\Core\ORM\EntityManager;
use Espo\Core\ServiceFactory;
use Atro\Core\Utils\Config;
use Espo\ORM\Entity;

class ActionManager
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getExecuteAsUserId(Entity $action, \stdClass $input): ?string
    {
        switch ($action->get('executeAs')) {
            case 'system':
                return 'system';
            case 'sameUser':
                return null;
        }

        return null;
    }

    public function canExecute(Entity $action, \stdClass $input): bool
    {
        return $this->getActionType($action->get('type'))->canExecute($action, $input);
    }

    public function executeNow(Entity $action, \stdClass $input): bool
    {
        $actionType = $this->getActionType($action->get('type'));

        if (property_exists($input, 'where') && $actionType->useMassActions($action, $input)) {
            $data = [
                'actionId'     => $action->get('id'),
                'sourceEntity' => $action->get('sourceEntity'),
                'where'        => $input->where,
            ];

            unset($input->where);
            foreach ($input as $key => $value) {
                if (!in_array($key, ['massAction', 'actionId'])) {
                    $data[$key] = $value;
                }
            }

            $params = [
                'action'             => 'action',
                'maxCountWithoutJob' => property_exists($input, 'actionSetLinkerId') ? 0 : $this->getConfig()->get('massUpdateMaxCountWithoutJob', 200),
                'maxChunkSize'       => $this->getConfig()->get('massUpdateMaxChunkSize', 3000),
                'minChunkSize'       => $this->getConfig()->get('massUpdateMinChunkSize', 400),
                'where'              => json_decode(json_encode($data['where']), true),
                'additionalJobData'  => $data,
            ];

            $this->getServiceFactory()->create($action->get('sourceEntity'))->executeMassAction($params,
                function ($id) use ($action, $input) {
                    $newInput = clone $input;
                    $newInput->entityId = $id;
                    $this->executeNow($action, $newInput);
                });

            return true;
        }

        if (!$this->canExecute($action, $input)) {
            return false;
        }

        // prepare current user ID
        $currentUserId = $this->container->get('user')->get('id');
        $userChanged = false;

        if (
            empty($this->getMemoryStorage()->get('importJobId'))
            && !empty($userId = $this->getExecuteAsUserId($action, $input))
        ) {
            $userChanged = $this->auth($userId);
        }

        $execution = $this->getEntityManager()->getRepository('ActionExecution')->get();
        $execution->set('actionId', $action->get('id'));
        $execution->set('actionName', $action->get('name'));
        $execution->set('action', $action);

        if (!empty($input->executedViaWorkflow)) {
            $execution->set('type', 'workflow');
            $workflow = $this->getEntityManager()->getRepository('Workflow')->get($input->workflowData['workflow_id']);
            if (!empty($workflow)) {
                $execution->set('name', $workflow->get('name'));
                $execution->set('workflowId', $workflow->get('id'));
            }
        } elseif (!empty($input->executedViaWebhook)) {
            $execution->set('type', 'incomingWebhook');
            if (!empty($input->webhook)) {
                $execution->set('name', $input->webhook->get('name'));
                $execution->set('incomingWebhookId', $input->webhook->get('id'));
            }
        } elseif (!empty($input->executedViaScheduledJob)) {
            $execution->set('type', 'scheduledJob');
            $scheduledJob = $this->getEntityManager()->getRepository('ScheduledJob')->get($input->job->get('scheduledJobId'));
            if (!empty($scheduledJob)) {
                $execution->set('name', $scheduledJob->get('name'));
                $execution->set('scheduledJobId', $scheduledJob->get('id'));
            }
        } else {
            $execution->set('name', $action->get('name'));
            $execution->set('type', 'manual');
        }

        $execution->set('status', 'running');
        $execution->set('payload', $this->preparePayload(clone $input));
        $this->getEntityManager()->saveEntity($execution);

        try {
            $res = $actionType->execute($execution, $input);
        } catch (\Throwable $e) {
            $res = false;
            $execution->set('status', 'failed');
            $execution->set('statusMessage', $e->getMessage());
            $this->getEntityManager()->saveEntity($execution);
        }

        if ($userChanged) {
            // auth as current user again
            $this->auth($currentUserId);
        }

        if (!empty($e)) {
            throw $e;
        }

        return $res;
    }

    protected function preparePayload(\stdClass $input): \stdClass
    {
        foreach ($input as $key => $value) {
            $input->$key = $this->processDataRecursively($value);
        }

        return $input;
    }

    /**
     * Recursively processes the data to replace objects with their class names.
     *
     * @param mixed $data
     * @return mixed
     */
    protected function processDataRecursively(mixed $data): mixed
    {
        if (is_object($data)) {
            // Replace the object with its fully qualified class name
            return "object [" . get_class($data) . "]";
        }

        if (is_array($data)) {
            // Recursively process each element in the array
            foreach ($data as $key => $value) {
                $data[$key] = $this->processDataRecursively($value);
            }

            return $data;
        }

        // Return scalar values as-is
        return $data;
    }

    protected function auth(string $userId): bool
    {
        $user = $this->getEntityManager()->getRepository('User')->get($userId);
        if (empty($user)) {
            return false;
        }
        if ($user->isSystem()) {
            $user->set('isAdmin', true);
            $user->set('ipAddress', $_SERVER['REMOTE_ADDR'] ?? null);
        }
        $this->getEntityManager()->setUser($user);
        $this->container->setUser($user);
        $this->container->get('acl')->setUser($user);

        return true;
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMemoryStorage(): MemoryStorage
    {
        return $this->container->get('memoryStorage');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    public function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getActionType(string $type): TypeInterface
    {
        $className = $this->getMetadata()->get(['action', 'types', $type]);
        if (empty($className)) {
            throw new Error("No such action type '$type'.");
        }

        return $this->container->get($className);
    }
}
