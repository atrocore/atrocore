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

use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Util;
use Espo\Jobs\UpdateCurrencyExchangeViaECB;
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
        if (empty($options['cascadeChange']) && $entity->getFetched('isMain') === true && $entity->get('isMain') === false) {
            $unit = $this
                ->select(['id'])
                ->where(['measureId' => $entity->get('measureId'), 'isMain' => true, 'id!=' => $entity->get('id')])
                ->findOne();

            if (empty($unit)) {
                throw new BadRequest($this->getInjection('language')->translate('defaultIsRequired', 'exceptions', 'Unit'));
            }
        }

        if ($entity->isNew()) {
            if ($entity->get('isMain') && !empty($this->where(['measureId' => $entity->get('measureId'), 'isMain' => true])->findOne())) {
                $entity->set('isMain', false);
            }

            if (empty($this->where(['measureId' => $entity->get('measureId')])->findOne())) {
                $entity->set('isMain', true);
                $entity->set('multiplier', 1);
            }
        }

        parent::beforeSave($entity, $options);

        // recalculate multiplier
        if ($entity->getFetched('isMain') === false && $entity->get('isMain') === true) {
            $k = 1 / $entity->getFetched('multiplier');
            foreach ($this->where(['measureId' => $entity->get('measureId'), 'id!=' => $entity->get('id')])->find() as $unit) {
                $unit->set('multiplier', round($k * $unit->get('multiplier'), 4));
                $unit->set('isMain', false);
                $this->getEntityManager()->saveEntity($unit, ['cascadeChange' => true]);
            }
            $entity->set('multiplier', 1);
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->validateBeforeRemove($entity);

        if (empty($options['skipIsMainValidation'])) {
            if ($entity->get('isMain')) {
                throw new BadRequest($this->getInjection('language')->translate('defaultIsRequired', 'exceptions', 'Unit'));
            }
        }

        parent::beforeRemove($entity, $options);
    }

    public function validateBeforeRemove(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs']) as $entityName => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDef) {
                if (
                    empty($fieldDef['notStorable'])
                    && empty($fieldDef['unitIdField'])
                    && empty($fieldDef['unitField'])
                    && !empty($fieldDef['measureId'])
                    && $fieldDef['measureId'] === $entity->get('measureId')
                ) {
                    $record = $this->getConnection()->createQueryBuilder()
                        ->select('t.*')
                        ->from($this->getConnection()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName))), 't')
                        ->where('t.' . Util::toUnderScore($field) . '_unit_id = :unitId')
                        ->andWhere('t.deleted = :false')
                        ->setParameter('unitId', $entity->get('id'))
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->fetchAssociative();

                    if (!empty($record)) {
                        throw new BadRequest(
                            sprintf(
                                $this->getLanguage()->translate('unitIsUsed', 'exceptions', 'Unit'),
                                $entity->get('name'),
                                $this->getLanguage()->translate($field, 'fields', $entity->getEntityType()),
                                $entityName,
                                $record['name'] ?? ''
                            )
                        );
                    }
                }
            }
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        foreach ($this->where(['convertToId' => $entity->get('id')])->find() as $unit) {
            $unit->set('convertToId', null);
            $this->getEntityManager()->saveEntity($unit, ['cascadeChange' => true]);
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options); // TODO: Change the autogenerated stub

        if ($entity->isNew() && $entity->get('measureId') === 'currency') {
            // update currency rates
            $this->getInjection('container')->get(UpdateCurrencyExchangeViaECB::class)->updateCurrencyRates();
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
