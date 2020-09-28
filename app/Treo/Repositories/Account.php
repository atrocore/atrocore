<?php

namespace Treo\Repositories;

use Espo\ORM\Entity;

/**
 * Class Account
 *
 * @package Treo\Repositories
 */
class Account extends \Espo\Core\ORM\Repositories\RDB
{
    /**
     * @param Entity $entity
     * @param $foreign
     * @param $data
     * @param array $options
     */
    protected function afterRelateContacts(Entity $entity, $foreign, $data, array $options = [])
    {
        if (!($foreign instanceof Entity)) {
            return;
        }

        if (!$foreign->get('accountId')) {
            $foreign->set('accountId', $entity->id);
            $this->getEntityManager()->saveEntity($foreign);
        }
    }

    /**
     * @param Entity $entity
     * @param array $options
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        $contacts = $entity->get('contacts');
        foreach ($contacts as $contact) {
            $this->removeAccountIdContact($entity, $contact);
        }
        parent::afterRemove($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param $foreign
     * @param array $options
     */
    protected function afterUnrelateContacts(Entity $entity, $foreign, array $options = [])
    {
        if (!($foreign instanceof Entity)) {
            return;
        }
        $this->removeAccountIdContact($entity, $foreign);
    }

    /**
     * @param Entity $account
     * @param Entity $contact
     */
    private function removeAccountIdContact(Entity $account, Entity $contact): void
    {
        if ($contact->get('accountId') && $contact->get('accountId') === $account->id) {
            $contact->set('accountId', null);
            $this->getEntityManager()->saveEntity($contact);
        }
    }
}
