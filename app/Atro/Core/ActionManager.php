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
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\Error;
use Atro\Core\KeyValueStorages\MemoryStorage;
use Espo\Core\Interfaces\Injectable;
use Espo\Core\ORM\EntityManager;
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

    public function executeNow(Entity $action, \stdClass $input): bool
    {
        // prepare current user ID
        $currentUserId = $this->container->get('user')->get('id');
        $userChanged = false;

        if (empty($this->getMemoryStorage()->get('importJobId')) &&
            !empty($userId = $this->getExecuteAsUserId($action, $input))) {
            $userChanged = $this->auth($userId);
        }

        $res = $this->getActionType($action->get('type'))->executeNow($action,$input);

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

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMemoryStorage(): MemoryStorage
    {
        return $this->container->get('memoryStorage');
    }

    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    public function getActionById(string $id): Entity
    {
        return $this->getEntityManager()->getEntity('Action', $id);
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