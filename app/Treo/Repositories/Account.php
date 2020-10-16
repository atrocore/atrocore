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
