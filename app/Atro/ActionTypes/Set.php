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

        $first = null;
        $parentId = null;

        foreach ($collection as $entity) {
            $job = $this->getEntityManager()->getEntity('ActionSetJob');

            $job->set('actionSetId', $action->get('id'));
            $job->set('actionId', $entity->get('actionId'));
            $job->set('state', 'Pending');
            $job->set('parentId', $parentId);

            $this->getEntityManager()->saveEntity($job);

            if (empty($first)) {
                $first = $job;
            }

            $parentId = $job->get('id');
        }

        if (!empty($first)) {
            $this->getEntityManager()->getRepository('ActionSetJob')->executeAction($first);
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
