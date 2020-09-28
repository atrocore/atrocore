<?php

declare(strict_types = 1);

namespace Treo\Services;

/**
 * Interface DashletInterface
 *
 * @package Treo\Services
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
interface DashletInterface
{
    /**
     * Get dashlet data
     *
     * @return array
     */
    public function getDashlet(): array;
}
