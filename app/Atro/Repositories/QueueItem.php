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

namespace Atro\Repositories;

use Atro\ActionTypes\Set;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\QueueManager;
use Atro\Core\Templates\Repositories\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\DataManager;
use Espo\ORM\Entity;
use Atro\Services\QueueManagerBase;
use Atro\Services\QueueManagerServiceInterface;

class QueueItem extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!$entity->isNew() && $entity->isAttributeChanged('status')) {
            $transitions = [
                "Running"  => [
                    "Pending"
                ],
                "Success"  => [
                    "Running"
                ],
                "Canceled" => [
                    "Pending",
                    "Running"
                ],
                "Failed"   => [
                    "Pending",
                    "Running"
                ],
                "Pending"  => [
                    "Success",
                    "Canceled",
                    "Failed"
                ]
            ];

            if (!isset($transitions[$entity->get('status')])) {
                throw new Error("Unknown status '{$entity->get('status')}'.");
            }

            if (!in_array($entity->getFetched('status'), $transitions[$entity->get('status')])) {
                throw new Error("It is impossible to change the status from '{$entity->getFetched('status')}' to '{$entity->get('status')}'.");
            }
        }

        // update sort order
        if ($entity->isNew()) {
            $sortOrder = time() - (new \DateTime('2023-01-01'))->getTimestamp();
            if (!empty($entity->get('parentId')) && !empty($parent = $this->get($entity->get('parentId')))) {
                $sortOrder = $parent->get('sortOrder') . '.' . $sortOrder;
            }
            $entity->set('sortOrder', $sortOrder);
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (empty($entity->get('startFrom')) || $entity->get('startFrom') <= (new \DateTime())->format('Y-m-d H:i:s')) {
            /**
             * Create file
             */
            if ($entity->get('status') === 'Pending') {
                $sortOrder = $this->get($entity->get('id'))->get('sortOrder');
                $priority = $entity->get('priority');

                $filePath = $this->getFilePath($sortOrder, $priority, $entity->get('id'));
                if (!empty($filePath)) {
                    file_put_contents($filePath, $entity->get('id'));
                }
                file_put_contents(QueueManager::FILE_PATH, '1');
            }
        }

        if (!in_array($entity->get('status'), ['Pending', 'Running'])) {
            $this->notify($entity);
        }

        if ($entity->get('status') === 'Canceled' && !empty($entity->get('pid'))) {
            exec("kill -9 {$entity->get('pid')}");
        }

        $this->preparePublicDataForMassAction($entity);

        if (in_array($entity->get('status'), ['Success', 'Failed', 'Canceled'])) {
            $item = $this->where(['status' => ['Pending', 'Running']])->findOne();
            if (empty($item) && file_exists(QueueManager::FILE_PATH)) {
                unlink(QueueManager::FILE_PATH);
            }
        }

        if (in_array($entity->get('status'), ['Success', 'Failed'])) {
            $className = $this->getMetadata()->get(['action', 'types', 'set']);
            if (empty($className)) {
                return;
            }

            /** @var Set $actionTypeService */
            $actionTypeService = $this->getInjection('container')->get($className);
            $actionTypeService->checkQueueItem($entity);
        }

        if ($entity->isAttributeChanged('status') && $entity->get('serviceName') === 'MassActionCreator') {
            if (!empty($entity->get('data')) && !empty($entity->get('data')->entityName)) {
                switch ($entity->get('status')) {
                    case 'Running':
                        QueueManagerBase::updatePublicData('entityMessage', $entity->get('data')->entityName, [
                            "qmId"    => $entity->get('id'),
                            "message" => $this->getInjection('language')
                                ->translate("prepareRecordsFor" . ucfirst($entity->get('data')->action))
                        ]);
                        break;
                    case 'Success':
                    case 'Failed':
                    case 'Canceled':
                        QueueManagerBase::updatePublicData('entityMessage', $entity->get('data')->entityName, null);
                        break;
                }

                if ($entity->get('status') === 'Canceled') {
                    $actionItems = $this
                        ->where([
                            'data*'  => '%"creatorId":"' . $entity->get('id') . '"%',
                            'status' => 'Pending'
                        ])
                        ->find();
                    foreach ($actionItems as $qi) {
                        $qi->set('status', 'Canceled');
                        $this->getEntityManager()->saveEntity($qi);
                    }
                }
            }
        }
    }

    public function getFilePath(float $sortOrder, string $priority, string $itemId): ?string
    {
        $filesInDir = 4000;
        $dirName = (int)($sortOrder / $filesInDir);

        $fileName = str_pad((string)($sortOrder % $filesInDir), 4, '0', STR_PAD_LEFT);
        $parts = explode('.', (string)$sortOrder);
        if (isset($parts[1])) {
            $fileName .= '_' . str_pad((string)((int)$parts[1]), 4, '0', STR_PAD_LEFT);
        }

        switch ($priority) {
            case 'Highest':
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/0';
                break;
            case 'High':
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/000001';
                break;
            case 'Low':
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/88888888888888';
                break;
            case 'Lowest':
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/99999999999999';
                break;
            default:
                $dirName = str_pad((string)$dirName, 6, '0', STR_PAD_LEFT);
                if (in_array($dirName, ['000000', '000001'])) {
                    $dirName = '000002';
                }
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/' . $dirName;
        }

        // create new dir if not exist
        while (!file_exists($dirPath)) {
            mkdir($dirPath, 0777, true);
        }

        if (file_exists($dirPath) && count(scandir($dirPath)) > $filesInDir) {
            return null;
        }

        return $dirPath . '/' . $fileName . '(' . $itemId . ')' . '.txt';
    }

    protected function preparePublicDataForMassAction(Entity $entity): void
    {
        if (!in_array($entity->get('serviceName'), ['MassDelete', 'MassRestore', 'MassUpdate'])) {
            return;
        }

        if (in_array($entity->get('status'), ['Pending', 'Running']) || empty($entity->get('data'))) {
            return;
        }

        $data = json_decode(json_encode($entity->get('data')), true);

        if (!empty($data['entityType'])) {
            $publicData = DataManager::getPublicData(lcfirst($entity->get('serviceName')));
            if (!empty($publicData[$data['entityType']]['jobIds'])) {
                $jobIds = $publicData[$data['entityType']]['jobIds'];
                $ongoingJob = $this->getConnection()
                    ->createQueryBuilder()
                    ->from('queue_item')
                    ->select('id')
                    ->where('id IN (:jobIds)')
                    ->andWhere('status IN (:status)')
                    ->andWhere('deleted=:false')
                    ->setParameter('jobIds', $jobIds, Mapper::getParameterType($jobIds))
                    ->setParameter('status', ['Pending', 'Running'], Connection::PARAM_STR_ARRAY)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchOne();

                if (empty($ongoingJob)) {
                    QueueManagerBase::updatePublicData(lcfirst($entity->get('serviceName')), $data['entityType'], null);
                }
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('status') == 'Running') {
            throw new BadRequest($this->getInjection('language')->translate('jobIsRunning', 'exceptions', 'QueueItem'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $fileName = $this->getFilePath($entity->get('sortOrder'), $entity->get('priority'), $entity->get('id'));
        if (!empty($fileName) && file_exists($fileName)) {
            unlink($fileName);
        }

        if ($entity->get('serviceName') === 'MassActionCreator') {
            $actionItems = $this
                ->where([
                    'data*'  => '%"creatorId":"' . $entity->get('id') . '"%',
                    'status' => 'Pending'
                ])
                ->find();
            foreach ($actionItems as $qi) {
                $qi->set('status', 'Canceled');
                $this->getEntityManager()->saveEntity($qi);
            }
        }

        $this->deleteFromDb($entity->get('id'));
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('queueManager');
        $this->addDependency('language');
        $this->addDependency('serviceFactory');
    }

    protected function notify(Entity $entity): void
    {
        try {
            $service = $this->getInjection('serviceFactory')->create($entity->get('serviceName'));
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Notification Error: ' . $e->getMessage());
            return;
        }

        if ($service instanceof QueueManagerServiceInterface && !empty($message = $service->getNotificationMessage($entity))) {
            $notification = $this->getEntityManager()->getEntity('Notification');
            $notification->set('type', 'Message');
            $notification->set('relatedType', 'QueueItem');
            $notification->set('relatedId', $entity->get('id'));
            $notification->set('message', $message);
            $notification->set('userId', $entity->get('createdById'));
            $this->getEntityManager()->saveEntity($notification);
        }
    }
}
