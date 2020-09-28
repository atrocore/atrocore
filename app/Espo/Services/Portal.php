<?php

namespace Espo\Services;

use \Espo\ORM\Entity;

class Portal extends Record
{
    protected $getEntityBeforeUpdate = true;

    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);
        $this->loadUrlField($entity);
    }

    public function loadAdditionalFieldsForList(Entity $entity)
    {
        parent::loadAdditionalFieldsForList($entity);
        $this->loadUrlField($entity);
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        $this->loadUrlField($entity);
    }

    protected function loadUrlField(Entity $entity)
    {
        $this->getRepository()->loadUrlField($entity);
    }
}

