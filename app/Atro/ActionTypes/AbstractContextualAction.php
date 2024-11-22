<?php

namespace Atro\ActionTypes;

use Atro\Core\Container;
use Atro\Core\KeyValueStorages\StorageInterface;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Auth;
use Espo\ORM\Entity;

abstract class AbstractContextualAction implements TypeInterface
{
    protected Container $container;
    protected static array $containerMap = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getRunningUserId(Entity $action, \stdClass $input): ?string
    {
        switch ($action->get('executeAs')) {
            case 'sameUser':
                return null;
            case 'system':
                return 'system';
        }

        return null;
    }

    public function getContainerForAction(Entity $action, \stdClass $input)
    {
        if (!empty($this->getMemoryStorage()->get('importJobId'))) {
            return $this->container;
        }

        $userId = $this->getRunningUserId($action, $input);
        if (empty($userId)) {
            return $this->container;
        }

        if (empty(self::$containerMap[$userId])) {
            $container = (new \Atro\Core\Application())->getContainer();
            $auth = new Auth($container);
            if ($userId === 'system') {
                $auth->useNoAuth();
            } else {
                /* @var $em EntityManager */
                $em = $container->get('entityManager');
                $user = $em->getRepository('User')->get($userId);
                if (empty($user)) {
                    return $this->container;
                }
                $user->set('ipAddress', $_SERVER['REMOTE_ADDR']);
                $em->setUser($user);
                $container->setUser($user);
            }
            self::$containerMap[$userId] = $container;
        }
        return self::$containerMap[$userId];
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->container->get('memoryStorage');
    }
}