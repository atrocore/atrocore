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

declare(strict_types=1);

namespace Espo\Services;

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class Sharing extends Base
{
    protected $mandatorySelectAttributeList = ['active', 'validTill', 'allowedUsage', 'used'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('link', $this->getConfig()->get('siteUrl') . '/?entryPoint=sharing&id=' . $entity->get('id'));

        $availableViaValidTill = empty($entity->get('validTill')) || $entity->get('validTill') >= (new \DateTime())->format('Y-m-d H:i:s');
        $availableViaAllowedUsage = empty($entity->get('allowedUsage')) || (int)$entity->get('used') < $entity->get('allowedUsage');

        $entity->set('available', !empty($entity->get('active')) && $availableViaValidTill && $availableViaAllowedUsage);
    }
}
