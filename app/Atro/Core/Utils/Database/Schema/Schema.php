<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Utils\Database\Schema;

use Atro\Core\Container;
use Espo\Core\EventManager\Event;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\File\ClassParser;
use Espo\Core\Utils\Metadata\OrmMetadata;
use Doctrine\DBAL\Schema\Schema as SchemaDBAL;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;

class Schema
{
    private Container $container;
    private Config $config;
    private Metadata $metadata;
    private EntityManager $entityManager;
    private ClassParser $classParser;
    private OrmMetadata $ormMetadata;
    private Connection $connection;
    private Converter $schemaConverter;
    private Comparator $comparator;

    protected ?array $rebuildActionClasses = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $container->get('config');
        $this->metadata = $container->get('metadata');
        $this->entityManager = $container->get('entityManager');
        $this->classParser = $container->get('classParser');
        $this->ormMetadata = $container->get('ormMetadata');
        $this->connection = $container->get('connection');
        $this->schemaConverter = $container->get(Converter::class);
        $this->comparator = new Comparator();
    }

    public function rebuild(): bool
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = $this->schemaConverter->createSchema();

        // init rebuild actions
        $this->initRebuildActions($fromSchema, $toSchema);

        // execute rebuild actions
        $this->executeRebuildActions('beforeRebuild');

        // get queries
        $queries = $this->getDiffSql($fromSchema, $toSchema);

        // prepare queries
        $queries = $this->dispatch('Schema', 'prepareQueries', new Event(['queries' => $queries]))->getArgument('queries');

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
        $result = $this
            ->dispatch('Schema', 'afterRebuild', new Event(['result' => (bool)$result, 'queries' => $queries]))
            ->getArgument('result');

        return $result;
    }

    public function getDiffQueries(): array
    {
        // set strict type
        $this->getPlatform()->strictType = true;

        $fromSchema = $this->getCurrentSchema();
        $toSchema = $this->schemaConverter->createSchema();
        $diff = $this->comparator->compare($fromSchema, $toSchema);

        // get queries
        $queries = $diff->toSql($this->getPlatform());

        // prepare queries
        $queries = $this->dispatch('Schema', 'prepareQueries', new Event(['queries' => $queries]))->getArgument('queries');

        // set strict type
        $this->getPlatform()->strictType = false;

        return $queries;
    }

    /**
     * Dispatch an event
     *
     * @param string $target
     * @param string $action
     * @param Event  $event
     *
     * @return mixed
     */
    protected function dispatch(string $target, string $action, Event $event)
    {
        /** @var \Atro\Core\EventManager\Manager $eventManager */
        $eventManager = $this->container->get('eventManager');
        if (!empty($eventManager)) {
            return $eventManager->dispatch($target, $action, $event);
        }

        return $event;
    }

    public function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    public function getCurrentSchema(): SchemaDBAL
    {
        return $this->connection->createSchemaManager()->createSchema();
    }

    public function toSql(SchemaDiff $schema): array
    {
        return $schema->toSaveSql($this->getPlatform());
    }

    public function getDiffSql(SchemaDBAL $fromSchema, SchemaDBAL $toSchema): array
    {
        $schemaDiff = $this->comparator->compareSchemas($fromSchema, $toSchema);

        return $this->toSql($schemaDiff);
    }

    protected function initRebuildActions($currentSchema = null, $metadataSchema = null): void
    {
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
