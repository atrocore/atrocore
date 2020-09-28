<?php

declare(strict_types=1);

namespace Treo\Repositories;

use Espo\Repositories\Email as EspoEmail;
use Espo\ORM\Entity;

/**
 * Class Email
 *
 * @author r.zablodskiy@treolabs.com
 */
class Email extends EspoEmail
{
    /**
     * Prepare addressess
     *
     * @param Entity $entity
     * @param string $type
     * @param bool $addAssignedUser
     */
    protected function prepareAddressess(Entity $entity, $type, $addAssignedUser = false)
    {
        if (!$entity->has($type) || empty($entity->get($type))) {
            return;
        }

        parent::prepareAddressess($entity, $type, $addAssignedUser);
    }
}
