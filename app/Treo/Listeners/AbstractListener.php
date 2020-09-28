<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Core\ORM\EntityManager;
use Espo\Core\Services\Base as BaseService;
use Treo\Core\Utils\Config;
use Treo\Core\Utils\Language;
use Treo\Services\AbstractService;

/**
 * AbstractListener class
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
abstract class AbstractListener
{
    use \Treo\Traits\ContainerTrait;

    /**
     * @var array
     */
    protected $services = [];

    /**
     * Get service
     *
     * @param string $name
     *
     * @return BaseService|AbstractService
     */
    protected function getService(string $name)
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this
                ->getContainer()
                ->get('serviceFactory')
                ->create($name);
        }

        return $this->services[$name];
    }

    /**
     * Get entity manager
     *
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    /**
     * Get config
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    /**
     * Get language
     *
     * @return Language
     */
    protected function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }

    /**
     * Get metadata
     *
     * @return \Treo\Core\Utils\Metadata
     */
    protected function getMetadata(): \Treo\Core\Utils\Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
