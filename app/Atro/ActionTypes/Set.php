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

use Atro\Core\Container;
use Atro\Core\EventManager\Event;
use Atro\Services\Action;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Set implements TypeInterface
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function executeViaWorkflow(array $workflowData, Event $event): bool
    {
        $action = $this->getEntityManager()->getEntity('Action', $workflowData['id']);
        $input = new \stdClass();

        return $this->executeNow($action, $input);
    }

    public function executeNow(Entity $action, \stdClass $input): bool
    {
        $linker = $this->getEntityManager()->getRepository('ActionSetLinker')
            ->where([
                'setId'     => $action->get('id'),
                'isActive'  => true
            ])
            ->order('sortOrder', 'ASC')
            ->findOne();

        if (empty($linker)) {
            return false;
        }

        $this->executeAction($linker, $input);

        return true;
    }

    public function executeAction(Entity $current, \stdClass $input): bool
    {
        if ($current->getEntityType() != 'ActionSetLinker') {
            return false;
        }

        if (empty($action = $current->get('action'))) {
            return false;
        }

        /** @var Action $actionService */
        $actionService = $this->getServiceFactory()->create('Action');

        try {
            if (property_exists($input, 'actionSetLinkerId')) {
                unset($input->actionSetLinkerId);
            }

            $input->actionSetLinkerId = $current->get('id');

            $actionService->executeNow($action->get('id'), $input);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("Set Action failed: " . $e->getMessage());
            return false;
        }

        if (empty($action->get('inBackground')) && !empty($next = $this->getNextAction($current))) {
            return $this->executeAction($next, $input);
        }

        return true;
    }

    public function getNextAction(Entity $entity): ?Entity
    {
        if ($entity->getEntityType() != 'ActionSetLinker') {
            return null;
        }

        return $this
            ->getEntityManager()
            ->getRepository('ActionSetLinker')
            ->where([
                'setId'         => $entity->get('setId'),
                'sortOrder>'    => $entity->get('sortOrder'),
                'isActive'      => true
            ])
            ->order('sortOrder', 'ASC')
            ->findOne();
    }

    public function checkQueueItem(Entity $entity): void
    {
        if ($entity->getEntityType() != 'QueueItem') {
            return;
        }

        if (!preg_match("/\"actionSetLinkerId\":\"([a-z0-9]*)\"/", json_encode($entity->get('data')), $matches)) {
            return;
        }

        $actionSetLinkerId = $matches[1];

        $current = $this->getEntityManager()->getEntity('ActionSetLinker', $actionSetLinkerId);

        if (empty($current)) {
            return;
        }

        $exist = $this
            ->getEntityManager()
            ->getRepository('QueueItem')
            ->where([
                'status'    => ['Pending', 'Running'],
                'data*'     => '%"actionSetLinkerId":"' . $current->get('id') . '"%'
            ])
            ->find();

        if (count($exist) == 0 && !empty($next = $this->getNextAction($current))) {
            $data = $entity->get('data');
            $data = empty($data) ? [] : Json::decode(Json::encode($data), true);

            $where = $this->searchValueByKey($data, 'where');

            $this->executeAction($next, (object)['where' => $where]);
        }
    }

    protected function searchValueByKey($array, $key)
    {
        if (!is_array($array)) {
            return [];
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach ($array as $subArray) {
            if (is_array($subArray)) {
                $result = $this->searchValueByKey($subArray, $key);
                if ($result !== []) {
                    return $result;
                }
            }
        }

        return [];
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }
}
