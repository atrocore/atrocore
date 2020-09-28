<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow as Item;
use Treo\Core\Workflow\MarkingStore\MethodMarkingStore;

/**
 * Class Workflow
 *
 * @author r.ratsun@gmail.com
 */
class Workflow extends Base
{
    /**
     * @inheritDoc
     */
    public function load()
    {
        // create registry
        $registry = new Registry();

        // get metadata
        $metadata = $this->getContainer()->get('metadata');

        if (!empty($workflows = $metadata->get('workflow', []))) {
            // get entity manager
            $entityManager = $this->getContainer()->get('entityManager');

            // get event manager
            $eventManager = $this->getContainer()->get('eventManager');

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
                        new Item($definition, new MethodMarkingStore(true, $field), $eventManager, $id),
                        new InstanceOfSupportStrategy($entityManager->normalizeEntityName($entity))
                    );
                }
            }
        }

        return $registry;
    }
}
