<?php

declare(strict_types=1);

namespace ColoredFields;

use Treo\Core\ModuleManager\AbstractEvent;

/**
 * Class Event
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Event extends AbstractEvent
{
    /**
     * @inheritdoc
     */
    public function afterInstall(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
    }
}
