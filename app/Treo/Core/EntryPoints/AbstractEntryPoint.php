<?php

declare(strict_types=1);

namespace Treo\Core\EntryPoints;

use Espo\Core\Acl;
use Espo\Core\Utils\ClientManager;
use Espo\Core\Utils\DateTime;
use Espo\Core\Utils\NumberUtil;
use Espo\Entities\User;
use Treo\Core\Container;
use Treo\Core\ORM\EntityManager;
use Treo\Core\ServiceFactory;
use Treo\Core\Utils\Config;
use Treo\Core\Utils\File\Manager;
use Treo\Core\Utils\Language;
use Treo\Core\Utils\Metadata;

/**
 * Class AbstractEntryPoint
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
abstract class AbstractEntryPoint
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var bool
     */
    public static $authRequired = true;

    /**
     * @var bool
     */
    public static $notStrictAuth = false;

    /**
     * AbstractEntryPoint constructor.
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

    /**
     * @return User
     */
    protected function getUser(): User
    {
        return $this->getContainer()->get('user');
    }

    /**
     * @return Acl
     */
    protected function getAcl(): Acl
    {
        return $this->getContainer()->get('acl');
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    /**
     * @return ServiceFactory
     */
    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getContainer()->get('serviceFactory');
    }

    /**
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    /**
     * @return DateTime
     */
    protected function getDateTime(): DateTime
    {
        return $this->getContainer()->get('dateTime');
    }

    /**
     * @return NumberUtil
     */
    protected function getNumber(): NumberUtil
    {
        return $this->getContainer()->get('number');
    }

    /**
     * @return Manager
     */
    protected function getFileManager(): Manager
    {
        return $this->getContainer()->get('fileManager');
    }

    /**
     * @return Language
     */
    protected function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }

    /**
     * @return ClientManager
     */
    protected function getClientManager(): ClientManager
    {
        return $this->getContainer()->get('clientManager');
    }
}
