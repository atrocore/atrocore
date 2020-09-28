<?php
declare(strict_types=1);

namespace Treo\Repositories;

use Espo\ORM\Entity;

/**
 * Class QueueItem
 *
 * @author r.ratsun@zinitsolutions.com
 */
class QueueItem extends \Espo\Core\Templates\Repositories\Base
{
    /**
     * @inheritdoc
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        // call parent
        parent::afterSave($entity, $options);

        // unset
        if (in_array($entity->get('status'), ['Canceled', 'Closed'])) {
            $this->unsetItem((int)$entity->get('stream'), (string)$entity->get('id'));
        }
    }

    /**
     * @inheritdoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        // call parent
        parent::afterRemove($entity, $options);

        // unset
        $this->unsetItem((int)$entity->get('stream'), (string)$entity->get('id'));
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        // call parent
        parent::init();

        $this->addDependency('queueManager');
    }

    /**
     * @param int    $stream
     * @param string $id
     */
    protected function unsetItem(int $stream, string $id): void
    {
        $this->getInjection('queueManager')->unsetItem($stream, $id);
    }
}
