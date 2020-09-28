<?php

namespace Treo\Services;

use \Espo\ORM\Entity;

/**
 * Class Person
 *
 * @package Treo\Services
 */
class Person extends \Espo\Services\Record
{
    /**
     * @param Entity $entity
     * @param        $data
     *
     * @return array|bool
     */
    protected function getDuplicateWhereClause(Entity $entity, $data)
    {
        $data = array(
            'OR' => []
        );
        $toCheck = false;
        if ($entity->get('firstName') || $entity->get('lastName')) {
            $part = [];
            $part['firstName'] = $entity->get('firstName');
            $part['lastName'] = $entity->get('lastName');
            $data['OR'][] = $part;
            $toCheck = true;
        }
        if (($entity->get('emailAddress') || $entity->get('emailAddressData'))
            && ($entity->isNew() || $entity->isAttributeChanged('emailAddress')
                || $entity->isAttributeChanged('emailAddressData'))) {
            if ($entity->get('emailAddress')) {
                $list = [$entity->get('emailAddress')];
            }
            if ($entity->get('emailAddressData')) {
                foreach ($entity->get('emailAddressData') as $row) {
                    if (!in_array($row->emailAddress, $list)) {
                        $list[] = $row->emailAddress;
                    }
                }
            }
            foreach ($list as $emailAddress) {
                $data['OR'][] = array(
                    'emailAddress' => $emailAddress
                );
                $toCheck = true;
            }
        }
        if (!$toCheck) {
            return false;
        }

        return $data;
    }
}
