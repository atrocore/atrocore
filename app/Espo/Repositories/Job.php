<?php

namespace Espo\Repositories;

use Espo\ORM\Entity;

class Job extends \Espo\Core\ORM\Repositories\RDB
{
    protected $hooksDisabled = true;

    protected $processFieldsAfterSaveDisabled = true;

    protected $processFieldsBeforeSaveDisabled = true;

    protected $processFieldsAfterRemoveDisabled = true;

    protected function init()
    {
        parent::init();
        $this->addDependency('config');
    }

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    public function beforeSave(Entity $entity, array $options = array())
    {
        if (!$entity->has('executeTime') && $entity->isNew()) {
            $entity->set('executeTime', date('Y-m-d H:i:s'));
        }

        if (!$entity->has('attempts') && $entity->isNew()) {
            $attempts = $this->getConfig()->get('jobRerunAttemptNumber', 0);
            $entity->set('attempts', $attempts);
        }
    }
}
