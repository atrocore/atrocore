<?php
/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore GmbH.
 *
 * This Software is the property of AtroCore GmbH and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

declare(strict_types=1);

namespace Atro\ActionTypes;

use Atro\Core\Container;
use Atro\Core\EventManager\Event;
use Atro\Services\Action;
use Espo\Core\ServiceFactory;
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
        $collection = $this->getEntityManager()->getRepository('ActionSetLinker')
            ->where([
                'setId'    => $action->get('id'),
                'isActive' => true
            ])
            ->order('sortOrder', 'ASC')
            ->find();

        if (empty($collection[0])) {
            return false;
        }

        /** @var Action $actionService */
        $actionService = $this->getServiceFactory()->create('Action');
        foreach ($collection as $entity) {
            try {
                $actionService->executeNow($entity->get('actionId'), new \stdClass());
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Set Action failed: " . $e->getMessage());
            }
        }

        return true;
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