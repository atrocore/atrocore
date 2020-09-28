<?php

declare(strict_types=1);

namespace Treo\Core\ModuleManager;

/**
 * Class AbstractEvent
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
abstract class AbstractEvent
{
    use \Treo\Traits\ContainerTrait;

    /**
     * After module install event
     */
    abstract public function afterInstall(): void;

    /**
     * After module delete event
     */
    abstract public function afterDelete(): void;
}
