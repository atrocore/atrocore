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

declare(strict_types=1);

namespace Espo\Core\Factories;

use Atro\Core\Container;
use Atro\Core\Factories\FactoryInterface as Factory;
use Espo\Core\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow as Item;

class Workflow implements Factory
{
    public function create(Container $container)
    {
        // create registry
        $registry = new Registry();

        /** @var \Espo\Core\Utils\Metadata $metadata */
        $metadata = $container->get('metadata');

        if (!empty($workflows = $metadata->get('workflow', []))) {
            // get entity manager
            $entityManager = $container->get('entityManager');

            /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
            $eventDispatcher = $container->get('eventManager')->getEventDispatcher();

            foreach ($workflows as $entity => $data) {
                foreach ($data as $field => $settings) {
                    // get places
                    $places = $metadata->get(['entityDefs', $entity, 'fields', $field, 'options']);

                    // prepare definition
                    $definitionBuilder = (new DefinitionBuilder())->addPlaces($places);
                    foreach ($settings['transitions'] as $to => $froms) {
                        foreach ((array)$froms as $from) {
                            $definitionBuilder->addTransition(new Transition($from . '_' . $to, $from, $to));
                        }
                    }

                    // set conditions
                    if (!empty($settings['conditions'][$from . '_' . $to])) {
                        // prepare conditions
                        $conditions = [
                            'conditions' => $settings['conditions'][$from . '_' . $to]
                        ];

                        $definitionBuilder->setMetadataStore(new InMemoryMetadataStore([$from . '_' . $to => $conditions]));
                    }

                    $definition = $definitionBuilder->build();

                    // prepare id
                    $id = $entity . '_' . $field;

                    // add
                    $registry->addWorkflow(
                        new Item($definition, new MethodMarkingStore(true, $field), $eventDispatcher, $id),
                        new InstanceOfSupportStrategy($entityManager->normalizeEntityName($entity))
                    );
                }
            }
        }

        return $registry;
    }
}
