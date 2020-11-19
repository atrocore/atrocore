<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Core\AclManager;
use Treo\Core\EventManager\Event;

/**
 * Class TeamEntity
 *
 * @package Treo\Listeners
 */
class TeamEntity extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws \Espo\Core\Exceptions\Exception
     */
    public function afterSave(Event $event)
    {
        $entity = $event->getArgument('entity');

        if ($entity->isAttributeChanged('rolesIds')) {
            $this
                ->getAclManager()
                ->clearAclCache();
        }
    }

    /**
     * @param Event $event
     *
     * @throws \Espo\Core\Exceptions\Exception
     */
    public function afterRelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'users') {
            $this
                ->getAclManager()
                ->clearAclCache();
        }
    }

    /**
     * @param Event $event
     *
     * @throws \Espo\Core\Exceptions\Exception
     */
    public function afterUnrelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'users') {
            $this
                ->getAclManager()
                ->clearAclCache();
        }
    }

    /**
     * @return AclManager
     */
    protected function getAclManager(): AclManager
    {
        return $this->getContainer()->get('aclManager');
    }
}
