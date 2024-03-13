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

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class Note extends \Espo\Core\Acl\Base
{
    protected $deleteThresholdPeriod = '1 month';

    protected $editThresholdPeriod = '7 days';

    public function checkIsOwner(EntityUser $user, Entity $entity)
    {
        if ($entity->get('type') === 'Post' && $user->id === $entity->get('createdById')) {
            return true;
        }
        return false;
    }

    public function checkEntityEdit(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->checkEntity($user, $entity, $data, 'edit')) {
            if ($this->checkIsOwner($user, $entity)) {
                $createdAt = $entity->get('createdAt');
                if ($createdAt) {
                    $noteEditThresholdPeriod = '-' . $this->getConfig()->get('noteEditThresholdPeriod', $this->editThresholdPeriod);
                    $dt = new \DateTime();
                    $dt->modify($noteEditThresholdPeriod);
                    try {
                        if ($dt->format('U') > (new \DateTime($createdAt))->format('U')) {
                            return false;
                        }
                    } catch (\Exception $e) {
                        return false;
                    }
                }
            }
            return true;
        }

        return false;
    }

    public function checkEntityDelete(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->checkEntity($user, $entity, $data, 'delete')) {
            if ($this->checkIsOwner($user, $entity)) {
                $createdAt = $entity->get('createdAt');
                if ($createdAt) {
                    $deleteThresholdPeriod = '-' . $this->getConfig()->get('noteDeleteThresholdPeriod', $this->deleteThresholdPeriod);
                    $dt = new \DateTime();
                    $dt->modify($deleteThresholdPeriod);
                    try {
                        if ($dt->format('U') > (new \DateTime($createdAt))->format('U')) {
                            return false;
                        }
                    } catch (\Exception $e) {
                        return false;
                    }
                }
            }
            return true;
        }

        return false;
    }
}
