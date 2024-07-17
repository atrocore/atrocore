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
use Espo\Core\EventManager\Event;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Espo\Core\Utils\Database\Orm\Converter as OrmConverter;
use Espo\Core\ORM\EntityManager;
use Doctrine\DBAL\Schema\Schema as SchemaDBAL;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;

class Schema
{
    private EventManager $eventManager;
    private EntityManager $entityManager;
    private Connection $connection;
    private Converter $schemaConverter;
    private Comparator $comparator;
    private OrmConverter $ormConverter;

    public function __construct(Container $container)
    {
        $this->eventManager = $container->get('eventManager');
        $this->entityManager = $container->get('entityManager');
        $this->connection = $container->get('connection');
        $this->schemaConverter = $container->get(Converter::class);
        $this->comparator = new Comparator();

        $this->ormConverter = new OrmConverter($container->get('metadata'), $container->get('fileManager'), $container->get('config'));
    }

    public function rebuild(): bool
    {
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

        $this->createSystemUser();

        return $this->eventManager
            ->dispatch('Schema', 'afterRebuild', new Event(['result' => (bool)$result, 'queries' => $queries]))
            ->getArgument('result');
    }

    protected function createSystemUser(): bool
    {
        $entity = $this->entityManager->getEntity('User', 'system');
        if (!isset($entity)) {
            $entity = $this->entityManager->getEntity('User');
            $entity->set([
                'id'        => 'system',
                'userName'  => 'system',
                'firstName' => '',
                'lastName'  => 'System',
            ]);
            $this->entityManager->saveEntity($entity);
        }

        return true;
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
}
