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

namespace Espo\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class Unit
 */
class Unit extends Base
{
    /**
     * @inheritDoc
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('measure')) {
            throw new Forbidden();
        }

        // default disabling
        if (empty($options['cascadeChange']) && $entity->getFetched('isDefault') === true && $entity->get('isDefault') === false) {
            $unit = $this
                ->select(['id'])
                ->where(['measureId' => $entity->get('measureId'), 'isDefault' => true, 'id!=' => $entity->get('id')])
                ->findOne();

            if (empty($unit)) {
                throw new BadRequest($this->getInjection('language')->translate('defaultIsRequired', 'exceptions', 'Unit'));
            }
        }

        if ($entity->isNew()) {
            if ($entity->get('isDefault') && !empty($this->where(['measureId' => $entity->get('measureId'), 'isDefault' => true])->findOne())) {
                throw new BadRequest($this->getInjection('language')->translate('newUnitCanNotBeDefault', 'exceptions', 'Unit'));
            }

            if (empty($this->where(['measureId' => $entity->get('measureId')])->findOne())) {
                $entity->set('isDefault', true);
                $entity->set('multiplier', 1);
            }
        }

        parent::beforeSave($entity, $options);

        // recalculate multiplier
        if ($entity->getFetched('isDefault') === false && $entity->get('isDefault') === true) {
            $k = 1 / $entity->getFetched('multiplier');
            foreach ($this->where(['measureId' => $entity->get('measureId'), 'id!=' => $entity->get('id')])->find() as $unit) {
                $unit->set('multiplier', $k * $unit->get('multiplier'));
                $unit->set('isDefault', false);
                $this->getEntityManager()->saveEntity($unit, ['cascadeChange' => true]);
            }
            $entity->set('multiplier', 1);
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if (empty($options['skipIsDefaultValidation'])) {
            if ($entity->get('isDefault')) {
                throw new BadRequest($this->getInjection('language')->translate('defaultIsRequired', 'exceptions', 'Unit'));
            }
            throw new BadRequest($this->getInjection('language')->translate('unitCannotBeDeleted', 'exceptions', 'Unit'));
        }
        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        foreach ($this->where(['convertToId' => $entity->get('id')])->find() as $unit) {
            $unit->set('convertToId', null);
            $this->getEntityManager()->saveEntity($unit, ['cascadeChange' => true]);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
