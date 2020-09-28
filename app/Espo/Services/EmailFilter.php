<?php

namespace Espo\Services;

use \Espo\ORM\Entity;

use \Espo\Core\Exceptions\Forbidden;

class EmailFilter extends Record
{
    protected function beforeCreateEntity(Entity $entity, $data)
    {
        parent::beforeCreateEntity($entity, $data);
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }
    }
}
