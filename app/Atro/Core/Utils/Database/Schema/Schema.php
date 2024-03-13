<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Utils\Database\Schema;

use Atro\Core\Container;
use Atro\Core\EventManager\Manager as EventManager;
use Atro\Core\Utils\Database\DBAL\FieldTypes\JsonArrayType;
use Atro\Core\Utils\Database\DBAL\FieldTypes\JsonObjectType;
use Espo\Core\EventManager\Event;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Database\Orm\Converter as OrmConverter;
use Espo\Core\Utils\Metadata;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\File\ClassParser;
use Doctrine\DBAL\Schema\Schema as SchemaDBAL;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Types\Type;

class Schema
{
    private EventManager $eventManager;
    private Config $config;
    private Metadata $metadata;
    private EntityManager $entityManager;
    private ClassParser $classParser;
    private Connection $connection;
    private Converter $schemaConverter;
    private Comparator $comparator;
    private OrmConverter $ormConverter;

    protected ?array $rebuildActionClasses = null;

    public function __construct(Container $container)
    {
        $this->eventManager = $container->get('eventManager');
        $this->config = $container->get('config');
        $this->metadata = $container->get('metadata');
        $this->entityManager = $container->get('entityManager');
        $this->classParser = $container->get('classParser');
        $this->connection = $container->get('connection');
        $this->schemaConverter = $container->get(Converter::class);
        $this->comparator = new Comparator();

        $this->ormConverter = new OrmConverter($container->get('metadata'), $container->get('fileManager'), $container->get('config'));
    }

    public function rebuild(): bool
    {
        // init rebuild actions
        $this->initRebuildActions();

        // execute rebuild actions
        $this->executeRebuildActions('beforeRebuild');

        // get queries
        $queries = $this->getDiffQueries(false);

        // run rebuild
        $result = true;
        foreach ($queries as $sql) {
            $GLOBALS['log']->info('SCHEMA, Execute Query: ' . $sql);
            try {
                $result &= (bool)$this->connection->executeQuery($sql);
            } catch (\Exception $e) {
                $GLOBALS['log']->alert('Rebuild database fault: ' . $e);
                $result = false;
            }
        }

        // execute rebuild action
        $this->executeRebuildActions('afterRebuild');

        // after rebuild action
        $result = $this->eventManager
            ->dispatch('Schema', 'afterRebuild', new Event(['result' => (bool)$result, 'queries' => $queries]))
            ->getArgument('result');

        return $result;
    }

    public function getDiffQueries(bool $strictType = true): array
    {
        if ($strictType) {
            $this->getPlatform()->strictType = true;
        }

        $fromSchema = $this->getCurrentSchema();
        $toSchema = $this->schemaConverter->createSchema();

        $diff = $this->comparator->compareSchemas($fromSchema, $toSchema);

        $queries = $diff->toSql($this->getPlatform());

        if ($strictType) {
            $this->getPlatform()->strictType = false;
        }

        return $queries;
    }

    public function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    public function getCurrentSchema(): SchemaDBAL
    {
        return $this->connection->createSchemaManager()->createSchema();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getSchemaConverter(): Converter
    {
        return $this->schemaConverter;
    }

    public function getOrmConverter(): OrmConverter
    {
        return $this->ormConverter;
    }

    protected function initRebuildActions(): void
    {
        $currentSchema = $this->getCurrentSchema();
        $metadataSchema = $this->schemaConverter->createSchema();

        $methods = array('beforeRebuild', 'afterRebuild');

        $this->classParser->setAllowedMethods($methods);
        $rebuildActions = $this->classParser->getData(['corePath' => CORE_PATH . '/Espo/Core/Utils/Database/Schema/rebuildActions']);

        $classes = array();
        foreach ($rebuildActions as $actionName => $actionClass) {
            $rebuildActionClass = new $actionClass($this->metadata, $this->config, $this->entityManager);
            if (isset($currentSchema)) {
                $rebuildActionClass->setCurrentSchema($currentSchema);
            }
            if (isset($metadataSchema)) {
                $rebuildActionClass->setMetadataSchema($metadataSchema);
            }

            foreach ($methods as $methodName) {
                if (method_exists($rebuildActionClass, $methodName)) {
                    $classes[$methodName][] = $rebuildActionClass;
                }
            }
        }

        $this->rebuildActionClasses = $classes;
    }

    protected function executeRebuildActions($action = 'beforeRebuild'): void
    {
        if (!isset($this->rebuildActionClasses)) {
            $this->initRebuildActions();
        }

        if (isset($this->rebuildActionClasses[$action])) {
            foreach ($this->rebuildActionClasses[$action] as $rebuildActionClass) {
                $rebuildActionClass->$action();
            }
        }
    }
}
