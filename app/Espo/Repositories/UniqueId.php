<?php

namespace Espo\Repositories;

use Espo\ORM\Entity;

class UniqueId extends \Espo\Core\ORM\Repositories\RDB
{
    protected $hooksDisabled = true;

    protected $processFieldsAfterSaveDisabled = true;

    protected $processFieldsBeforeSaveDisabled = true;

    protected $processFieldsAfterRemoveDisabled = true;

    protected function getNewEntity()
    {
        $entity = parent::getNewEntity();
        $entity->set('name', \Espo\Core\Utils\Util::generateId());
        return $entity;
    }
}

