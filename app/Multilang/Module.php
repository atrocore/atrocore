<?php
declare(strict_types=1);

namespace Multilang;

use Treo\Core\ModuleManager\AbstractModule;

/**
 * Class Module
 *
 * @author r.ratsun <r.ratsun@gmail.com>
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
        return $this->path . 'app/Multilang/';
    }
}