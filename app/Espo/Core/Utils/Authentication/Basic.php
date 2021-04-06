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

declare(strict_types=1);

namespace Espo\Core\Utils\Authentication;

use Espo\Core\Exceptions\Error;
use Espo\Entities\AuthToken;
use Espo\Entities\User;

/**
 * Class Basic
 */
class Basic extends Base
{
    /**
     * @param string         $username
     * @param string         $password
     * @param AuthToken|null $authToken
     *
     * @return User|null
     * @throws Error
     */
    public function login(string $username, string $password, AuthToken $authToken = null): ?User
    {
        if (!$this->getConfig()->get('isModulesLoaded', true)) {
            throw new Error('Not all modules are loaded. Please try later.');
        }

        return $this
            ->getEntityManager()
            ->getRepository('User')
            ->where(
                [
                    'userName' => $username,
                    'password' => $authToken ? $authToken->get('hash') : $this->getPasswordHash()->hash($password)
                ]
            )
            ->findOne();
    }
}
