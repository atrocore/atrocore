<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Utils\Database\Schema;

use Espo\Core\Container;
use Espo\Core\EventManager\Event;

/**
 * Class Schema
 */
class Schema extends \Espo\Core\Utils\Database\Schema\Schema
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * Set container
     *
     * @param Container $container
     *
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function rebuild($entityList = null)
    {
        if (!$this->getConverter()->process()) {
            return false;
        }

        // get current schema
        $currentSchema = $this->getCurrentSchema();

        // get entityDefs
        $entityDefs = $this
            ->dispatch('Schema', 'prepareEntityDefsBeforeRebuild', new Event(['data' => $this->ormMetadata->getData()]))
            ->getArgument('data');

        // get metadata schema
        $metadataSchema = $this->schemaConverter->process($entityDefs, $entityList);

        // init rebuild actions
        $this->initRebuildActions($currentSchema, $metadataSchema);

        // execute rebuild actions
        $this->executeRebuildActions('beforeRebuild');

        // get queries
        $queries = $this->getDiffSql($currentSchema, $metadataSchema);

        // prepare queries
        $queries = $this->dispatch('Schema', 'prepareQueries', new Event(['queries' => $queries]))->getArgument('queries');

        // run rebuild
        $result = true;
        $connection = $this->getConnection();
        foreach ($queries as $sql) {
            $GLOBALS['log']->info('SCHEMA, Execute Query: ' . $sql);
            try {
                $result &= (bool)$connection->executeQuery($sql);
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
        $toSchema = $this->schemaConverter->process($this->ormMetadata->getData(), null);
        $diff = $this->getComparator()->compare($fromSchema, $toSchema);

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
        if (!empty($eventManager = $this->getContainer()->get('eventManager'))) {
            return $eventManager->dispatch($target, $action, $event);
        }

        return $event;
    }

    /**
     * Get container
     *
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }
}
