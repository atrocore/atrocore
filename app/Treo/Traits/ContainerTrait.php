<?php

declare(strict_types=1);

namespace Treo\Traits;

use Treo\Core\Container;

/**
 * Class ContainerTrait
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
trait ContainerTrait
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * Set container
     *
     * @param Container $container
     *
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Get container
     *
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }
}
