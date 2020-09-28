<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Database\Schema\Converter;
use Treo\Core\Utils\Database\Schema\Schema as Instance;
use Treo\Core\Utils\Config;
use Treo\Core\Utils\Metadata;
use Espo\Core\Utils\File\ClassParser;
use Espo\Core\Utils\File\Manager;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Metadata\OrmMetadata;

/**
 * Schema loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Schema extends Base
{

    /**
     * Load Schema
     *
     * @return Instance
     */
    public function load()
    {
        // prepare data
        $config = $this->getConfig();
        $metadata = $this->getMetadata();
        $fileManager = $this->getFileManager();
        $entityManager = $this->getEntityManager();
        $classParser = $this->getClassParser();
        $ormMetadata = $this->getOrmMetadata();

        // create
        $schema = $this->getSchema($config, $metadata, $fileManager, $entityManager, $classParser, $ormMetadata);

        // set container
        $schema->setContainer($this->getContainer());

        // set converter
        $schema->schemaConverter = new Converter($metadata, $fileManager, $schema, $config);

        return $schema;
    }

    /**
     * Get schema
     *
     * @param Config $config
     * @param Metadata $metadata
     * @param Manager $fileManager
     * @param EntityManager $entityManager
     * @param ClassParser $classParser
     * @param OrmMetadata $ormMetadata
     *
     * @return Instance
     */
    protected function getSchema(
        Config $config,
        Metadata $metadata,
        Manager $fileManager,
        EntityManager $entityManager,
        ClassParser $classParser,
        OrmMetadata $ormMetadata
    ) {
        return new Instance($config, $metadata, $fileManager, $entityManager, $classParser, $ormMetadata);
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
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->getContainer()->get('metadata');
    }

    /**
     * Get file manager
     *
     * @return Manager
     */
    protected function getFileManager()
    {
        return $this->getContainer()->get('fileManager');
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

    /**
     * Get class parser
     *
     * @return ClassParser
     */
    protected function getClassParser()
    {
        return $this->getContainer()->get('classParser');
    }

    /**
     * Get ORM metadata
     *
     * @return OrmMetadata
     */
    protected function getOrmMetadata()
    {
        return $this->getContainer()->get('ormMetadata');
    }
}
