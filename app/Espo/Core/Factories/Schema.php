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

namespace Espo\Core\Factories;

use Espo\Core\Container;
use Espo\Core\Interfaces\Factory;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\File\ClassParser;
use Espo\Core\Utils\File\Manager;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Metadata\OrmMetadata;
use Espo\Core\Utils\Database\Schema\Converter;
use Treo\Core\Utils\Database\Schema\Schema as Instance;

class Schema implements Factory
{
    public function create(Container $container)
    {
        $config = $container->get('config');
        $metadata = $container->get('metadata');
        $fileManager = $container->get('fileManager');
        $entityManager = $container->get('entityManager');
        $classParser = $container->get('classParser');
        $ormMetadata = $container->get('ormMetadata');

        // create
        $schema = $this->getSchema($config, $metadata, $fileManager, $entityManager, $classParser, $ormMetadata);

        // set container
        $schema->setContainer($container);

        // set converter
        $schema->schemaConverter = new Converter($metadata, $fileManager, $schema, $config);

        return $schema;
    }

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
}
