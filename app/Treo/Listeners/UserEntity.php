<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Core\AclManager;
use Treo\Core\EventManager\Event;

/**
 * Class UserEntity
 *
 * @package Treo\Listeners
 */
class UserEntity extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws \Espo\Core\Exceptions\Exception
     */
    public function afterSave(Event $event)
    {
        $entity = $event->getArgument('entity');

        if ($entity->isAttributeChanged('teamsIds')
            || $entity->isAttributeChanged('rolesIds')
            || $entity->isAttributeChanged('isAdmin')) {
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
