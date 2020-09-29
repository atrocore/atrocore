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
     * @inheritDoc
     */
    public static function getLoadOrder(): int
    {
        return 1;
    }

    /**
     * @return string
     */
    protected function getAppPath(): string
    {
        return $this->path . 'app/ColoredFields/';
    }
}
