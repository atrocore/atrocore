<?php
declare(strict_types=1);

namespace Treo\Core\Utils\Database\Schema;

use Treo\Traits\ContainerTrait;
use Treo\Core\EventManager\Event;

/**
 * Class Schema
 *
 * @author r.ratsun r.ratsun@treolabs.com
 */
class Schema extends \Espo\Core\Utils\Database\Schema\Schema
{
    use ContainerTrait;

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

        // berore rebuild action
        $queries = $this
            ->dispatch('Schema', 'beforeRebuild', new Event(['queries' => $queries]))
            ->getArgument('queries');

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

    /**
     * Get diff queries
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Espo\Core\Exceptions\Error
     */
    public function getDiffQueries(): array
    {
        // set strict type
        $this->getPlatform()->strictType = true;

        // get queries
        $queries = $this
            ->getComparator()
            ->compare($this->getCurrentSchema(), $this->schemaConverter->process($this->ormMetadata->getData(), null))
            ->toSql($this->getPlatform());

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
}
