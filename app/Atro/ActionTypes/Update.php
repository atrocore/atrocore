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

namespace Atro\ActionTypes;

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Services\Record;
use Espo\ORM\Entity;

class Update extends AbstractAction
{
    public function executeNow(Entity $action, \stdClass $input): bool
    {
        $sourceEntity = $this->getSourceEntity($action, $input);

        if (empty($action->get('applyToPreselectedRecords'))) {

            $whereJson = json_encode($this->getWhere($action) ?? []);

            $templateData = [
                'entity' => $sourceEntity
            ];

            $whereJson = $this->getTwig()->renderTemplate($whereJson, $templateData);
            $where = @json_decode($whereJson, true);

            /** @var \Espo\Core\SelectManagers\Base $selectManager */
            $selectManager = $this->container->get('selectManagerFactory')->create($action->get('searchEntity'));
            /** @var \Atro\Core\Templates\Repositories\Base $repository */
            $repository = $this->getEntityManager()->getRepository($action->get('searchEntity'));

            if (property_exists($input, 'queueData') && isset($input->queueData['targetIds'])) {
                $targetIds = $input->queueData['targetIds'];
                if (empty($targetIds)) {
                    return true;
                }
                $collection = $repository->findByIds($targetIds);
            } else {
                $selectParams = $selectManager->getSelectParams(['where' => $where], true, true);
                $repository->handleSelectParams($selectParams);
                $count = $repository->count($selectParams);

                if ($count === 0) {
                    return true;
                }

                if ($count > $this->container->get('config')->get('massUpdateMaxCountWithoutJob', 200)) {
                    // build chunks
                    $chunks = [];
                    $chunkSize = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);

                    $offset = 0;

                    $select = ['id'];
                    $orderBy = 'id';
                    if (!empty($this->getMetadata()->get(['entityDefs', $entityName, 'fields', 'createdAt']))) {
                        $orderBy = 'createdAt';
                        $select[] = $orderBy;
                    }

                    while (true) {
                        $collection = $repository
                            ->select($select)
                            ->limit($offset, $chunkSize)
                            ->order($orderBy)
                            ->find($selectParams);

                        $offset = $offset + $chunkSize;
                        $ids = array_column($collection->toArray(), 'id');
                        if (empty($ids)) {
                            break;
                        }

                        $chunks[] = $ids;
                    }

                    $data = [
                        'actionId'     => $action->get('id'),
                        'sourceEntity' => $action->get('sourceEntity'),
                        'ids'          => !empty($sourceEntity) ? [$sourceEntity->get('id')] : []
                    ];
                    if (property_exists($input, 'queueData') && !empty($input->queueData['actionSetLinkerId'])) {
                        $data['actionSetLinkerId'] = $input->queueData['actionSetLinkerId'];
                    } else if (property_exists($input, 'actionSetLinkerId')) {
                        $data['actionSetLinkerId'] = $input->actionSetLinkerId;
                    }

                    foreach ($chunks as $index => $ids) {
                        $jobEntity = $this->getEntityManager()->getEntity('Job');

                        $jobEntity->set([
                            'name'    => $action->get('name') . " (" . ($index + 1) . "/" . count($chunks) . ")",
                            'type'    => 'ActionHandler',
                            'payload' => array_merge($data, ['targetIds' => $ids])
                        ]);
                        $this->getEntityManager()->saveEntity($jobEntity);
                    }

                    return true;
                }

                $collection = $repository->find($selectParams);
            }


            $result = false;
            foreach ($collection as $entity) {
                try {
                    $repository->putToCache($entity->get('id'), $entity);
                    if ($this->updateEntity($entity, $sourceEntity, $action, $input)) {
                        $result = true;
                    }
                } catch (Forbidden $e) {
                } catch (NotFound $e) {
                } catch (BadRequest $e) {
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error("Update Action failed: " . $e->getMessage());
                }
            }
            return $result;
        } else {
            if (empty($sourceEntity)) {
                throw new BadRequest('Action can be executed only from Source Entity.');
            }
        }

        return $this->updateEntity($sourceEntity, $sourceEntity, $action, $input);
    }


    protected function updateEntity(Entity $entity, ?Entity $triggeredEntity, Entity $action, \stdClass $input): bool
    {
        $actionData = $action->get('data');

        if (empty($actionData->field) || empty($actionData->field->updateType)) {
            return false;
        }

        $inputData = null;
        switch ($actionData->field->updateType) {
            case 'basic':
                $inputData = $actionData->fieldData ?? null;
                break;
            case 'script':
                if (!empty($actionData->field->updateScript)) {
                    $templateData = [
                        'entity' => $entity
                    ];
                    if (!empty($triggeredEntity)) {
                        $templateData['triggeredEntityType'] = $triggeredEntity->getEntityType();
                        $templateData['triggeredEntityId'] = $triggeredEntity->get('id');
                        $templateData['triggeredEntity'] = $triggeredEntity;
                    }
                    $outputJson = $this->container->get('twig')->renderTemplate($actionData->field->updateScript, $templateData);
                    $inputData = @json_decode((string)$outputJson);
                    if (empty($inputData) && !empty(trim($outputJson))) {
                        throw new Error("Invalid Json for Update: " . $outputJson);
                    }
                }
                break;
        }

        if ($inputData === null) {
            return false;
        }

        $inputData->_workflowAction = !empty($input->triggeredEntityId);

        /** @var Record $service */
        $service = $this->getServiceFactory()->create($entity->getEntityType());
        if ($service instanceof Record) {
            /** @var Event $event */
            $event = $input->event ?? null;
            if (!empty($event) && !empty($event->getArgument('options')['pseudoTransactionId'])) {
                $service->setPseudoTransactionId($event->getArgument('options')['pseudoTransactionId']);
            }
        }
        $cacheKey = $this->getEntityManager()->getRepository($entity->getEntityType())->getCacheKey($entity->get('id'));
        $this->getMemoryStorage()->delete($cacheKey);

        try {
            $service->updateEntity($entity->get('id'), $inputData);
        } catch (NotModified $e) {
        }

        return true;
    }
}
