<?php

namespace Espo\ORM;

use \Espo\Core\Exceptions\Error;

class EntityManager
{
    /**
     * @var \PDO
     */
    protected $pdo;

    protected $entityFactory;

    protected $repositoryFactory;

    protected $mappers = array();

    protected $metadata;

    protected $repositoryHash = array();

    protected $params = array();

    protected $query;

    protected $driverPlatformMap = array(
        'pdo_mysql' => 'Mysql',
        'mysqli' => 'Mysql',
    );

    public function __construct($params)
    {
        $this->params = $params;

        $this->metadata = new Metadata();

        if (empty($this->params['platform'])) {
            if (empty($this->params['driver'])) {
                throw new \Exception('No database driver specified.');
            }
            $driver = $this->params['driver'];
            if (empty($this->driverPlatformMap[$driver])) {
                throw new \Exception("Database driver '{$driver}' is not supported.");
            }
            $this->params['platform'] = $this->driverPlatformMap[$this->params['driver']];
        }

        if (!empty($params['metadata'])) {
            $this->setMetadata($params['metadata']);
        }

        $entityFactoryClassName = '\\Espo\\ORM\\EntityFactory';
        if (!empty($params['entityFactoryClassName'])) {
            $entityFactoryClassName = $params['entityFactoryClassName'];
        }
        $this->entityFactory = new $entityFactoryClassName($this, $this->metadata);

        $repositoryFactoryClassName = '\\Espo\\ORM\\RepositoryFactory';
        if (!empty($params['repositoryFactoryClassName'])) {
            $repositoryFactoryClassName = $params['repositoryFactoryClassName'];
        }
        $this->repositoryFactory = new $repositoryFactoryClassName($this, $this->entityFactory);

        $this->init();
    }

    public function getQuery()
    {
        if (empty($this->query)) {
            $platform = $this->params['platform'];
            $className = '\\Espo\\ORM\\DB\\Query\\' . ucfirst($platform);
            $this->query = new $className($this->getPDO(), $this->entityFactory);
        }
        return $this->query;
    }

    protected function getMapperClassName($name)
    {
        $className = null;

        switch ($name) {
            case 'RDB':
                $platform = $this->params['platform'];
                $className = '\\Espo\\ORM\\DB\\' . ucfirst($platform) . 'Mapper';
                break;
        }

        return $className;
    }

    public function getMapper($name)
    {
        if ($name{0} == '\\') {
            $className = $name;
        } else {
            $className = $this->getMapperClassName($name);
        }

        if (empty($this->mappers[$className])) {
            $this->mappers[$className] = new $className($this->getPDO(), $this->entityFactory, $this->getQuery());
        }
        return $this->mappers[$className];
    }

    protected function initPDO()
    {
        $params = $this->params;

        $port = empty($params['port']) ? '' : 'port=' . $params['port'] . ';';

        $platform = strtolower($params['platform']);

        $options = array();
        if (isset($params['sslCA'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = $params['sslCA'];
        }
        if (isset($params['sslCert'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CERT] = $params['sslCert'];
        }
        if (isset($params['sslKey'])) {
            $options[\PDO::MYSQL_ATTR_SSL_KEY] = $params['sslKey'];
        }
        if (isset($params['sslCAPath'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CAPATH] = $params['sslCAPath'];
        }
        if (isset($params['sslCipher'])) {
            $options[\PDO::MYSQL_ATTR_SSL_CIPHER] = $params['sslCipher'];
        }

        $this->pdo = new \PDO($platform . ':host='.$params['host'].';'.$port.'dbname=' . $params['dbname'] . ';charset=' . $params['charset'], $params['user'], $params['password'], $options);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function getEntity($name, $id = null)
    {
        if (!$this->hasRepository($name)) {
            throw new Error("ORM: Repository '{$name}' does not exist.");
        }

        return $this->getRepository($name)->get($id);
    }

    public function saveEntity(Entity $entity, array $options = array())
    {
        $entityType = $entity->getEntityType();
        return $this->getRepository($entityType)->save($entity, $options);
    }

    public function removeEntity(Entity $entity, array $options = array())
    {
        $entityType = $entity->getEntityType();
        return $this->getRepository($entityType)->remove($entity, $options);
    }

    public function getRepository($name)
    {
        if (!$this->hasRepository($name)) {
            // TODO Throw error
        }

        if (empty($this->repositoryHash[$name])) {
            $this->repositoryHash[$name] = $this->repositoryFactory->create($name);
        }
        return $this->repositoryHash[$name];
    }

    public function setMetadata(array $data)
    {
        $this->metadata->setData($data);
    }

    public function hasRepository($name)
    {
        return $this->getMetadata()->has($name);
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getOrmMetadata()
    {
        return $this->getMetadata();
    }

    /**
     * @return \PDO
     */
    public function getPDO()
    {
        if (empty($this->pdo)) {
            $this->initPDO();
        }
        return $this->pdo;
    }

    public function normalizeRepositoryName($name)
    {
        return $name;
    }

    public function normalizeEntityName($name)
    {
        return $name;
    }

    public function createCollection($entityName, $data = array())
    {
        $seed = $this->getEntity($entityName);
        $collection = new EntityCollection($data, $seed, $this->entityFactory);
        return $collection;
    }

    public function getEntityFactory()
    {
        return $this->entityFactory;
    }

    /**
     * @param string $sql
     * @param array  $inputParams
     *
     * @return \PDOStatement
     */
    public function nativeQuery(string $sql, array $inputParams = []):\PDOStatement
    {
        // prepare params
        $params = null;
        if (!empty($inputParams)) {
            $params = [];
            foreach ($inputParams as $key => $value) {
                $params[':' . $key] = $value;
            }
        }

        $sth = $this
            ->getPDO()
            ->prepare($sql);
        $sth->execute($params);

        return $sth;
    }

    protected function init()
    {
    }
}

