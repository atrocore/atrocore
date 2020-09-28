<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Core\Interfaces\Loader;
use Treo\Core\Container;

/**
 * Base loader class
 *
 * @author r.ratsun@gmail.com
 */
abstract class Base implements Loader
{
    /**
     * @var Container
     */
    private $container;

    /**
     * Base constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }
}
