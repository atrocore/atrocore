<?php

declare(strict_types=1);

namespace Multilang\Listeners;

use Espo\ORM\Entity as OrmEntity;
use Treo\Core\EventManager\Event;
use Treo\Listeners\AbstractListener;

/**
 * Class Entity
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Entity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeSave(Event $event)
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
                foreach ($entity->get($field) as $value) {
                    $keys[] = array_search($value, $data['options']);
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
}
