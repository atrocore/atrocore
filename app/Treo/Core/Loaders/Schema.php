<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Database\Schema\Converter;
use Treo\Core\Utils\Database\Schema\Schema as Instance;
use Espo\Core\Utils\Config;
use Treo\Core\Utils\Metadata;
use Espo\Core\Utils\File\ClassParser;
use Espo\Core\Utils\File\Manager;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Metadata\OrmMetadata;

/**
 * Schema loader
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
