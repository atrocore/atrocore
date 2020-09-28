<?php

declare(strict_types=1);

namespace Treo\Repositories;

use Espo\Repositories\Job as Base;
use Espo\ORM\Entity;
use Treo\Core\EventManager\Event;

/**
 * Class Job
 *
 * @author r.ratsun@treolabs.com
 */
class Job extends Base
{
    /**
     * @inheritdoc
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        // dispatch an event
        $event = $this->dispatch('JobEntity', 'beforeSave', ['entity' => $entity, 'options' => $options]);

        // call parent
        parent::beforeSave($event->getArgument('entity'), $event->getArgument('options'));
    }

    /**
     * @inheritdoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        // dispatch an event
        $event = $this->dispatch('JobEntity', 'afterRemove', ['entity' => $entity, 'options' => $options]);

        // call parent
        parent::afterRemove($event->getArgument('entity'), $event->getArgument('options'));
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('eventManager');
    }

    /**
     * Dispatch an event
     *
     * @param string $target
     * @param string $action
     * @param array  $data
     *
     * @return Event
     */
    protected function dispatch(string $target, string $action, array $data = []): Event
    {
        return $this->getInjection('eventManager')->dispatch($target, $action, new Event($data));
    }
}
