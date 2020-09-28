<?php

namespace Espo\Repositories;

use Espo\ORM\Entity;

class Integration extends \Espo\Core\ORM\Repositories\RDB
{
    public function get($id = null)
    {
        $entity = parent::get($id);
        if (empty($entity) && !empty($id)) {
            $entity = $this->get();
            $entity->id = $id;
        }
        return $entity;
    }
}

