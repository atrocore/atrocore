<?php

namespace Espo\Services;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\NotFound;

class ActionHistoryRecord extends Record
{
    protected $actionHistoryDisabled = true;

    protected $listCountQueryDisabled = true;

    protected $forceSelectAllAttributes = true;

    public function loadParentNameFields(\Espo\ORM\Entity $entity)
    {
        if ($entity->get('targetId') && $entity->get('targetType')) {
            $repository = $this->getEntityManager()->getRepository($entity->get('targetType'));
            if ($repository) {
                $target = $repository->where(array(
                    'id' => $entity->get('targetId')
                ))->findOne(array(
                    'withDeleted' => true
                ));
                if ($target && $target->get('name')) {
                    $entity->set('targetName', $target->get('name'));
                }
            }
        }
    }
}

