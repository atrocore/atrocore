<?php

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class Note extends \Espo\Core\Acl\Base
{
    protected $deleteThresholdPeriod = '1 month';

    protected $editThresholdPeriod = '7 days';

    public function checkIsOwner(EntityUser $user, Entity $entity)
    {
        if ($entity->get('type') === 'Post' && $user->id === $entity->get('createdById')) {
            return true;
        }
        return false;
    }

    public function checkEntityEdit(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->checkEntity($user, $entity, $data, 'edit')) {
            if ($this->checkIsOwner($user, $entity)) {
                $createdAt = $entity->get('createdAt');
                if ($createdAt) {
                    $noteEditThresholdPeriod = '-' . $this->getConfig()->get('noteEditThresholdPeriod', $this->editThresholdPeriod);
                    $dt = new \DateTime();
                    $dt->modify($noteEditThresholdPeriod);
                    try {
                        if ($dt->format('U') > (new \DateTime($createdAt))->format('U')) {
                            return false;
                        }
                    } catch (\Exception $e) {
                        return false;
                    }
                }
            }
            return true;
        }

        return false;
    }

    public function checkEntityDelete(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->checkEntity($user, $entity, $data, 'delete')) {
            if ($this->checkIsOwner($user, $entity)) {
                $createdAt = $entity->get('createdAt');
                if ($createdAt) {
                    $deleteThresholdPeriod = '-' . $this->getConfig()->get('noteDeleteThresholdPeriod', $this->deleteThresholdPeriod);
                    $dt = new \DateTime();
                    $dt->modify($deleteThresholdPeriod);
                    try {
                        if ($dt->format('U') > (new \DateTime($createdAt))->format('U')) {
                            return false;
                        }
                    } catch (\Exception $e) {
                        return false;
                    }
                }
            }
            return true;
        }

        return false;
    }
}
