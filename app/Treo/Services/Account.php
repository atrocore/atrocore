<?php

namespace Treo\Services;

use \Espo\ORM\Entity;

/**
 * Class Account
 *
 * @package Treo\Services
 */
class Account extends \Espo\Services\Record
{
    protected $linkSelectParams = array(
        'contacts' => array(
            'additionalColumns' => array(
                'role' => 'accountRole',
                'isInactive' => 'accountIsInactive'
            )
        )
    );

    /**
     * @param Entity $entity
     * @param $data
     *
     * @return array|bool
     */
    protected function getDuplicateWhereClause(Entity $entity, $data)
    {
        if (!$entity->get('name')) {
            return false;
        }
        return array(
            'name' => $entity->get('name')
        );
    }

    /**
     * @param Entity $entity
     * @param array $sourceList
     * @param $attributes
     */
    protected function afterMerge(Entity $entity, array $sourceList, $attributes)
    {
        foreach ($sourceList as $source) {
            $contactList = $this->getEntityManager()->getRepository('Contact')->where([
                'accountId' => $source->id
            ])->find();
            foreach ($contactList as $contact) {
                $contact->set('accountId', $entity->id);
                $this->getEntityManager()->saveEntity($contact);
            }
        }
    }
}
