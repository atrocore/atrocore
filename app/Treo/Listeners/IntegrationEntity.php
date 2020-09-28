<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\EventManager\Event;

/**
 * Class IntegrationEntity
 *
 * @author r.ratsun@gmail.com
 */
class IntegrationEntity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // for GoogleMaps
        if ($entity->id === 'GoogleMaps') {
            if (!$entity->get('enabled') || !$entity->get('apiKey')) {
                $this->getConfig()->set('googleMapsApiKey', null);
                $this->getConfig()->save();
                return;
            }
            $this->getConfig()->set('googleMapsApiKey', $entity->get('apiKey'));
            $this->getConfig()->save();
        }
    }
}
