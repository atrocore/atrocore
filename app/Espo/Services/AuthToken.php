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

namespace Espo\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class AuthToken extends Record
{
    protected $internalAttributeList = ['hash', 'token'];

    protected $actionHistoryDisabled = true;

    protected $readOnlyAttributeList = ['lastAccess', 'token', 'hash', 'ipAddress'];

    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        parent::handleInput($data, $id);

        if ($id !== null) {
            foreach (['userId'] as $field) {
                if (property_exists($data, $field)) {
                    unset($data->$field);
                }
            }
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        foreach ($collection as $entity) {
            $entity->skipAuthTokenGeneration = true;
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->skipAuthTokenGeneration) && $this->getUser()->isAdmin()) {
            $entity->set('authToken', $this->getAuthorizationToken($entity->get('id')));
        }
    }

    public function getAuthorizationToken(string $authTokenId): ?string
    {
        $conn = $this->getEntityManager()->getConnection();
        $record = $conn->createQueryBuilder()
            ->select('at.token, u.user_name')
            ->from('auth_token', 'at')
            ->join('at', $conn->quoteIdentifier('user'), 'u', 'at.user_id = u.id')
            ->where('at.id = :id')
            ->setParameter('id', $authTokenId)
            ->fetchAssociative();

        if (empty($record)) {
            return null;
        }

        return base64_encode("{$record['user_name']}:{$record['token']}");
    }
}

