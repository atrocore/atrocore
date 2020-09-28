<?php

declare(strict_types=1);

namespace Treo\Traits;

use Treo\Core\EventManager;

/**
 * Class EventManagerTrait
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
trait EventManagerTrait
{
    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @param EventManager $eventManager
     *
     * @return mixed
     */
    public function setEventManager(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;

        return $this;
    }

    /**
     * @return EventManager
     */
    protected function getEventManager(): EventManager
    {
        return $this->eventManager;
    }
}
