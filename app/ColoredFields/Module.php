<?php

declare(strict_types=1);

namespace ColoredFields;

use Treo\Core\ModuleManager\AbstractModule;

/**
 * Class Module
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Module extends AbstractModule
{
    /**
     * @inheritdoc
     */
    public static function getLoadOrder(): int
    {
        return 5100;
    }
}
