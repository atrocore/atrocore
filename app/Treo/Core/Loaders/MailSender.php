<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Config;
use Espo\Core\ORM\EntityManager;

/**
 * MailSender loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class MailSender extends Base
{

    /**
     * Load MailSender
     *
     * @return mixed
     */
    public function load()
    {
        $className = $this
            ->getContainer()
            ->get('metadata')
            ->get('app.serviceContainer.classNames.mailSernder', '\\Espo\\Core\\Mail\\Sender');

        return $this->getMailSender($className);
    }

    /**
     * Get mail sender class
     *
     * @param string $className
     *
     * @return mixed
     */
    protected function getMailSender(string $className)
    {
        return new $className(
            $this->getConfig(),
            $this->getEntityManager()
        );
    }

    /**
     * Get config
     *
     * @return Config
     */
    protected function getConfig()
    {
        return $this->getContainer()->get('config');
    }

    /**
     * Get entity manager
     *
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getContainer()->get('entityManager');
    }
}
