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
 * Class Measure
 */
class Measure extends Base
{
    protected array $measureUnits = [];

    public function getMeasureUnits(string $measureId): array
    {
        if (!isset($this->measureUnits[$measureId])) {
            $units = $this->getEntityManager()->getRepository('Unit')
                ->where(['measureId' => $measureId])
                ->order('createdAt', 'ASC')
                ->find();

            $this->measureUnits[$measureId] = [];
            foreach ($units as $unit) {
                $this->measureUnits[$measureId][$unit->get('id')] = $unit;
            }
        }

        return $this->measureUnits[$measureId];
    }

    public function convertMeasureUnit($value, string $measureId, string $unitId): array
    {
        $units = $this->getMeasureUnits($measureId);
        if (!isset($units[$unitId])) {
            return [];
        }

        $result = [];
        foreach ($units as $unit) {
            $result[$unit->get('name')] = round($value / $units[$unitId]->get('multiplier') * $unit->get('multiplier'), 4);
        }

        return $result;
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'units') {
            throw new Forbidden();
        }

        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);
    }

    /**
     * @inheritDoc
     */
    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName == 'units') {
            throw new Forbidden();
        }

        parent::beforeUnrelate($entity, $relationName, $foreign, $options);
    }

    /**
     * @inheritDoc
     */
    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->validateBeforeRemove($entity);

        parent::beforeRemove($entity, $options);
    }

    public function validateBeforeRemove(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs']) as $entityName => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDef) {
                if (!empty($fieldDef['measureId']) && $fieldDef['measureId'] === $entity->get('id')) {
                    throw new BadRequest(
                        sprintf(
                            $this->getLanguage()->translate('measureIsUsed', 'exceptions', 'Measure'),
                            $entity->get('name'),
                            $this->getLanguage()->translate($field, 'fields', $entity->getEntityType()),
                            $entityName
                        )
                    );
                }
            }
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        foreach ($entity->get('units') as $unit) {
            $this->getEntityManager()->removeEntity($unit, ['skipIsDefaultValidation' => true]);
        }

        parent::afterRemove($entity, $options);
    }
}
