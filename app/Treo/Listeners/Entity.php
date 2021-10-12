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

use Espo\Entities\User;
use Espo\Hooks\Common;
use Espo\ORM\Entity as OrmEntity;
use Treo\Core\EventManager\Event;

/**
 * Class Entity
 */
class Entity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeSave(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeSave', $event);

        // call multi-lang event
        $this->multiLang($event);

        $this->prepareTextEmptyValues($event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\CurrencyConverted::class)
                ->beforeSave($event->getArgument('entity'), $event->getArgument('options'));
            $this
                ->createHook(Common\Formula::class)
                ->beforeSave($event->getArgument('entity'), $event->getArgument('options'));
            $this
                ->createHook(Common\NextNumber::class)
                ->beforeSave($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterSave', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\Stream::class)
                ->afterSave($event->getArgument('entity'), $event->getArgument('options'));
            $this
                ->createHook(Common\StreamNotesAcl::class)
                ->afterSave($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param Event $event
     */
    public function beforeRemove(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRemove', $event);
    }

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterRemove', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\Stream::class)
                ->afterRemove($event->getArgument('entity'), $event->getArgument('options'));
        }
    }

    /**
     * @param Event $event
     */
    public function beforeMassRelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeMassRelate', $event);
    }

    /**
     * @param Event $event
     */
    public function afterMassRelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterMassRelate', $event);
    }

    /**
     * @param Event $event
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    public function beforeRelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeRelate', $event);
    }

    /**
     * @param Event $event
     */
    public function afterRelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterRelate', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\Stream::class)
                ->afterRelate(
                    $event->getArgument('entity'),
                    $event->getArgument('options'),
                    $this->getHookRelationData($event)
                );
        }
    }

    /**
     * @param Event $event
     */
    public function beforeUnrelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'beforeUnrelate', $event);
    }

    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        // delegate an event
        $this->dispatch($event->getArgument('entityType') . 'Entity', 'afterUnrelate', $event);

        // call hooks
        if (empty($event->getArgument('hooksDisabled')) && empty($event->getArgument('options')['skipHooks'])) {
            $this
                ->createHook(Common\Stream::class)
                ->afterUnrelate(
                    $event->getArgument('entity'),
                    $event->getArgument('options'),
                    $this->getHookRelationData($event)
                );
        }
    }

    /**
     * @param string $target
     * @param string $action
     * @param Event  $event
     */
    protected function dispatch(string $target, string $action, Event $event)
    {
        $this->getContainer()->get('eventManager')->dispatch($target, $action, $event);
    }

    /**
     * @param Event $event
     */
    protected function multiLang(Event $event)
    {
        /** @var OrmEntity $entity */
        $entity = $event->getArgument('entity');

        // get fields
        $fields = $this->getContainer()->get('metadata')->get(['entityDefs', $entity->getEntityType(), 'fields'], []);

        foreach ($fields as $field => $data) {
            if ($data['type'] == 'enum' && !empty($data['isMultilang']) && $entity->isAttributeChanged($field)) {
                // find key
                $key = array_search($entity->get($field), $data['options']);
                foreach ($fields as $mField => $mData) {
                    if (isset($mData['multilangField']) && $mData['multilangField'] == $field) {
                        if ($entity->get($field) == '') {
                            $value = $entity->get($field);
                        } elseif (isset($mData['options'][$key])) {
                            $value = $mData['options'][$key];
                        }

                        if (isset($value)) {
                            $entity->set($mField, $value);
                        }
                    }
                }
            }

            if ($data['type'] == 'multiEnum' && !empty($data['isMultilang']) && $entity->isAttributeChanged($field)) {
                $keys = [];
                if (!empty($data['options'])) {
                    foreach ($entity->get($field) as $value) {
                        $keys[] = array_search($value, $data['options']);
                    }
                }
                foreach ($fields as $mField => $mData) {
                    if (isset($mData['multilangField']) && $mData['multilangField'] == $field) {
                        $values = [];
                        foreach ($keys as $key) {
                            $values[] = $mData['options'][$key];
                        }
                        $entity->set($mField, $values);
                    }
                }
            }
        }
    }

    /**
     * @param Event $event
     */
    protected function prepareTextEmptyValues(Event $event): void
    {
        /** @var \Espo\Core\ORM\Entity $entity */
        $entity = $event->getArgument('entity');

        $textTypes = ['varchar', 'text', 'wysiwyg', 'url'];

        foreach ($this->getMetadata()->get(['entityDefs', $event->getArgument('entityType'), 'fields'], []) as $field => $defs) {
            if (in_array($defs['type'], $textTypes) && $entity->get($field) === '') {
                $entity->set($field);
            }
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

    /**
     * @param string $entity
     * @param string $relationName
     * @param string $id
     *
     * @return mixed
     */
    private function findForeignEntity(string $entity, string $relationName, string $id)
    {
        $foreignEntityName = $this
            ->getContainer()
            ->get('metadata')
            ->get(['entityDefs', $entity, 'links', $relationName, 'entity']);

        return (!empty($foreignEntityName)) ? $this->getEntityManager()->getEntity($foreignEntityName, $id) : null;
    }

    /**
     * @param Event $event
     *
     * @return array
     */
    private function getHookRelationData(Event $event): array
    {
        // prepare foreign
        $foreign = $event->getArgument('foreign');
        if (is_string($foreign)) {
            $foreign = $this->findForeignEntity(
                $event->getArgument('entity')->getEntityType(),
                $event->getArgument('relationName'),
                $foreign
            );
        }

        return [
            'relationName'  => $event->getArgument('relationName'),
            'relationData'  => $event->getArgument('relationData'),
            'foreignEntity' => $foreign,
        ];
    }
}
