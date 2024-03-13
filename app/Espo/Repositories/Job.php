<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\Repositories;

use Espo\Core\EventManager\Event;
use Espo\ORM\Entity;

class Job extends \Espo\Core\ORM\Repositories\RDB
{
    protected $hooksDisabled = true;

    protected $processFieldsAfterSaveDisabled = true;

    protected $processFieldsBeforeSaveDisabled = true;

    protected $processFieldsAfterRemoveDisabled = true;

    public function beforeSave(Entity $entity, array $options = array())
    {
        $event = $this->dispatch('JobEntity', 'beforeSave', ['entity' => $entity, 'options' => $options]);

        $entity = $event->getArgument('entity');
        $options = $event->getArgument('options');

        if (!$entity->has('executeTime') && $entity->isNew()) {
            $entity->set('executeTime', date('Y-m-d H:i:s'));
        }

        if (!$entity->has('attempts') && $entity->isNew()) {
            $attempts = $this->getConfig()->get('jobRerunAttemptNumber', 0);
            $entity->set('attempts', $attempts);
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $event = $this->dispatch('JobEntity', 'afterRemove', ['entity' => $entity, 'options' => $options]);

        $entity = $event->getArgument('entity');
        $options = $event->getArgument('options');

        parent::afterRemove($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('config');
        $this->addDependency('eventManager');
    }

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    protected function dispatch(string $target, string $action, array $data = []): Event
    {
        return $this->getInjection('eventManager')->dispatch($target, $action, new Event($data));
    }
}
