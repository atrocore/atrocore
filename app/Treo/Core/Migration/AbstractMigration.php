<?php

namespace Treo\Core\Migration;

use Treo\Core\Container;
use Treo\Core\ORM\EntityManager;

/**
 * AbstractMigration class
 *
 * @author     r.ratsun <r.ratsun@treolabs.com>
 *
 * @deprecated We will remove it after 01.01.2021
 */
class AbstractMigration extends Base
{
    /**
     * @var Container
     */
    private $container;

    /**
     * Set container
     *
     * @param Container $container
     *
     * @return AbstractMigration
     */
    public function setContainer(Container $container): AbstractMigration
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

    /**
     * Run rebuild action
     */
    protected function runRebuild(): void
    {
        $this->getContainer()->get('dataManager')->rebuild();
    }

    /**
     * Get entityManager
     *
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }
}
