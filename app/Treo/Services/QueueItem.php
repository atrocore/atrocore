<?php

declare(strict_types=1);

namespace Treo\Services;

use Espo\ORM\Entity;

/**
 * Class QueueItem
 *
 * @author r.ratsun@zinitsolutions.com
 */
class QueueItem extends \Espo\Core\Templates\Services\Base
{
    /**
     * @var array
     */
    private $services = [];

    /**
     * @inheritdoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        // prepare entity
        $entity->set('actions', $this->getItemActions($entity));
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    /**
     * @param Entity $entity
     *
     * @return array
     */
    protected function getItemActions(Entity $entity): array
    {
        // prepare result
        $result = [];

        // prepare service name
        $serviceName = $this->getServiceName($entity);

        // prepare action statuses
        $statuses = ['Pending', 'Running', 'Success', 'Failed'];

        if (in_array($entity->get('status'), $statuses)) {
            // create service
            if (!isset($this->services[$serviceName])) {
                $this->services[$serviceName] = $this->getServiceFactory()->create($serviceName);
            }

            // prepare methodName
            $methodName = "get" . $entity->get('status') . "StatusActions";

            // prepare result
            $result = $this->services[$serviceName]->$methodName($entity);
        }

        return $result;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'QueueItem');
    }

    /**
     * @param Entity $entity
     *
     * @return string
     */
    protected function getServiceName(Entity $entity): string
    {
        $serviceName = (string)$entity->get('serviceName');
        if (empty($serviceName) || !$this->checkExists($serviceName)) {
            $serviceName = 'QueueManagerBase';
        }

        return $serviceName;
    }

    /**
     * @param string $serviceName
     *
     * @return bool
     */
    protected function checkExists(string $serviceName): bool
    {
        return $this->getServiceFactory()->checkExists($serviceName);
    }
}
