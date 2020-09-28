<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * Class EntityManager
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class EntityManager extends Base
{
    /**
     * @inheritdoc
     */
    public function load()
    {
        // get config
        $config = $this->getContainer()->get('config');

        $params = [
            'host'                       => $config->get('database.host'),
            'port'                       => $config->get('database.port'),
            'dbname'                     => $config->get('database.dbname'),
            'user'                       => $config->get('database.user'),
            'charset'                    => $config->get('database.charset', 'utf8'),
            'password'                   => $config->get('database.password'),
            'metadata'                   => $this->getContainer()->get('ormMetadata')->getData(),
            'repositoryFactoryClassName' => $this->getRepositoryFactoryClassName(),
            'driver'                     => $config->get('database.driver'),
            'platform'                   => $config->get('database.platform'),
            'sslCA'                      => $config->get('database.sslCA'),
            'sslCert'                    => $config->get('database.sslCert'),
            'sslKey'                     => $config->get('database.sslKey'),
            'sslCAPath'                  => $config->get('database.sslCAPath'),
            'sslCipher'                  => $config->get('database.sslCipher')
        ];

        // get class name
        $className = $this->getEntityManagerClassName();

        $entityManager = new $className($params);
        $entityManager->setEspoMetadata($this->getContainer()->get('metadata'));
        $entityManager->setContainer($this->getContainer());

        return $entityManager;
    }

    /**
     * @return string
     */
    protected function getEntityManagerClassName(): string
    {
        return \Treo\Core\ORM\EntityManager::class;
    }

    /**
     * @return string
     */
    protected function getRepositoryFactoryClassName(): string
    {
        return \Espo\Core\ORM\RepositoryFactory::class;
    }
}
