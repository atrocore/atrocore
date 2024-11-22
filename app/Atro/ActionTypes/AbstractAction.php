<?php

namespace Atro\ActionTypes;

use Atro\Core\Container;
use Atro\Core\KeyValueStorages\MemoryStorage;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\QueueManager;
use Atro\Core\Twig\Twig;
use Atro\DTO\QueueItemDTO;
use Espo\Core\ORM\EntityManager;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Auth;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\System;
use Espo\ORM\Entity;

abstract class AbstractAction implements TypeInterface
{
    protected Container $container;
//    protected static Container $actionContainer;
//    protected Container $previousContainer;

    public function __construct(Container $container)
    {
//        $this->previousContainer = $container;
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

    abstract public function execute(Entity $action, \stdClass $input): bool;

    public function executeNow(Entity $action, \stdClass $input): bool
    {
        // prepare current user ID
        $currentUserId = $this->container->get('user')->get('id');
        $userChanged = false;

        if (empty($this->getMemoryStorage()->get('importJobId')) &&
            !empty($userId = $this->getExecuteAsUserId($action, $input))) {
            $userChanged = $this->auth($userId);
        }

        $res = $this->execute($action, $input);

        if ($userChanged) {
            // auth as current user again
            $this->auth($currentUserId);
        }
        return $res;
    }

    protected function auth(string $userId): bool
    {
        $user = $this->getEntityManager()->getRepository('User')->get($userId);
        if (empty($user)) {
            return false;
        }
        $this->getEntityManager()->setUser($user);
        $this->container->setUser($user);
        return true;
    }


//    public function initializeContainer(Entity $action, \stdClass $input): void
//    {
//        if (empty($this->previousContainer->get('memoryStorage')()->get('importJobId')) &&
//            !empty($userId = $this->getExecuteAsUserId($action, $input))) {
//
//            if (empty(self::$actionContainer)) {
//                self::$actionContainer = (new \Atro\Core\Application())->getContainer();
//            }
//
//            /* @var $em EntityManager */
//            $em = self::$actionContainer->get('entityManager');
//
//            if ($userId === 'system') {
//                (new Auth(self::$actionContainer))->useNoAuth();
//            } else {
//                $user = $em->getRepository('User')->get($userId);
//                if (empty($user)) {
//                    $this->container = $this->previousContainer;
//                    return;
//                }
//                $user->set('ipAddress', $_SERVER['REMOTE_ADDR']);
//                $em->setUser($user);
//                self::$actionContainer->setUser($user);
//            }
//            $this->container = self::$actionContainer;
//            return;
//        }
//
//        $this->container = $this->previousContainer;
//    }

    public function createQueueItem(Entity $action, \stdClass $input): bool
    {
        if (!property_exists($input, 'where')) {
            return false;
        }

        $data = ['actionId' => $action->get('id'), 'where' => $input->where];
        if (property_exists($input, 'actionSetLinkerId')) {
            $data['actionSetLinkerId'] = $input->actionSetLinkerId;
        }

        return $this->getQueueManager()->push(
            new QueueItemDTO($action->get('name'), 'QueueManagerActionHandler', $data)
        );
    }

    public function getSourceEntity($action, \stdClass $input): ?Entity
    {
        $sourceEntity = null;
        if (!empty($action->get('sourceEntity')) && property_exists($input, 'entityId')) {
            $sourceEntity = $this->getEntityManager()->getRepository($action->get('sourceEntity'))->get($input->entityId);
        } elseif (!empty($input->triggeredEntity)) {
            $sourceEntity = $input->triggeredEntity;
        } elseif (property_exists($input, 'triggeredEntityType') && property_exists($input, 'triggeredEntityId')) {
            $sourceEntity = $this->getEntityManager()->getRepository($input->triggeredEntityType)->get($input->triggeredEntityId);
        }

        return $sourceEntity;
    }

    public function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getTwig(): Twig
    {
        return $this->container->get('twig');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getQueueManager(): QueueManager
    {
        return $this->container->get('queueManager');
    }

    protected function getMemoryStorage(): MemoryStorage
    {
        return $this->container->get('memoryStorage');
    }

    public function getActionById(string $id): Entity
    {
        return $this->getEntityManager()->getEntity('Action', $id);
    }
}