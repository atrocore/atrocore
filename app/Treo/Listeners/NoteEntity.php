<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://treolabs.com
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

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Hooks\Note;
use Treo\Core\EventManager\Event;

/**
 * Class NoteEntity
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class NoteEntity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeSave(Event $event)
    {
        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Note\Mentions::class)
                ->beforeSave($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Note\Notifications::class)
                ->afterSave($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param string $className
     *
     * @return mixed
     */
    private function createHook(string $className)
    {
        $hook = new $className();
        foreach ($hook->getDependencyList() as $name) {
            $hook->inject($name, $this->getContainer()->get($name));
        }

        return $hook;
    }
}
