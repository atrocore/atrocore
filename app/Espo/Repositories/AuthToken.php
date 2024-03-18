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

namespace Espo\Repositories;

use Espo\Core\ORM\Repositories\RDB;
use  Atro\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Auth;
use Espo\ORM\Entity;

class AuthToken extends RDB
{
    protected $hooksDisabled = true;

    protected $processFieldsAfterSaveDisabled = true;

    protected $processFieldsBeforeSaveDisabled = true;

    protected $processFieldsAfterRemoveDisabled = true;

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isNew()) {
            if (empty($entity->get('userId'))) {
                throw new BadRequest('User ID is required');
            }

            $user = $this->getEntityManager()->getRepository('User')->get($entity->get('userId'));
            if (empty($user)) {
                throw new BadRequest('User is required');
            }

            $entity->set('token', Auth::generateToken());
            $entity->set('ipAddress', $_SERVER['REMOTE_ADDR'] ?? null);
            $entity->set('hash', $user->get('password'));

            if (empty($entity->get('name'))) {
                $entity->set('name', 'Login at ' . date('Y-m-d H:i:s'));
            }
        }
    }
}
