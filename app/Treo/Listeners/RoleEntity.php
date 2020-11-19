<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Core\AclManager;
use Treo\Core\EventManager\Event;

/**
 * Class RoleEntity
 *
 * @package Treo\Listeners
 */
class RoleEntity extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws \Espo\Core\Exceptions\Exception
     */
    public function afterSave(Event $event)
    {
        $this
            ->getAclManager()
            ->clearAclCache();
    }

    /**
     * @return AclManager
     */
    protected function getAclManager(): AclManager
    {
        return $this->getContainer()->get('aclManager');
    }
}
