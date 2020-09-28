<?php

namespace Espo\Repositories;

use Espo\ORM\Entity;

use \Espo\Core\Exceptions\Error;

class EmailFolder extends \Espo\Core\ORM\Repositories\RDB
{
    protected function beforeSave(Entity $entity, array $options = array())
    {
        parent::beforeSave($entity, $options);
        $order = $entity->get('order');
        if (is_null($order)) {
            $order = $this->max('order');
            if (!$order) {
                $order = 0;
            }
            $order++;
            $entity->set('order', $order);
        }
    }
}

