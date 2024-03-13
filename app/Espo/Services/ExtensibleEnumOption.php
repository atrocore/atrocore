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

use Atro\Core\Templates\Services\Base;
use Atro\ORM\DB\RDB\Mapper;
use Espo\ORM\Entity;

class ExtensibleEnumOption extends Base
{
    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_id') && property_exists($data, '_sortedIds') && property_exists($data, '_scope') && !empty($data->_sortedIds)) {
            $this->getEntityManager()->getRepository('ExtensibleEnumExtensibleEnumOption')->updateSortOrder($data->_id, $data->_sortedIds);
            return $this->getEntity($id);
        }

        return parent::updateEntity($id, $data);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($this->getMemoryStorage()->get('exportJobId')) && empty($this->getMemoryStorage()->get('importJobId'))  && $entity->get('listMultilingual') === null) {
           $hasMultilingual = $this->getEntityManager()
                ->getConnection()
                ->createQueryBuilder()
                ->from('extensible_enum','ee')
                ->join('ee','extensible_enum_extensible_enum_option','eeeeo', 'ee.id=eeeeo.extensible_enum_id')
                ->select('ee.id')
                ->where('eeeeo.extensible_enum_option_id=:id')
                ->where('ee.multilingual=:true')
                ->setParameter('id', $entity->get('id'), Mapper::getParameterType($entity->get('id')))
                ->setParameter('true',true, Mapper::getParameterType(true))
                ->fetchOne();

               $entity->set('listMultilingual', !empty($hasMultilingual));

        }
    }
}
