<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Jobs\UpdateCurrencyExchangeViaECB;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

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
                    && !empty($fieldDef['type'])
                    && !empty($fieldDef['measureId'])
                    && $fieldDef['measureId'] === $entity->get('measureId')
                ) {
                    $column = $fieldDef['type'] === 'measure' ? Util::toUnderScore($field) : Util::toUnderScore($field) . '_unit_id';
                    $record = $this->getConnection()->createQueryBuilder()
                        ->select('t.*')
                        ->from($this->getConnection()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName))), 't')
                        ->where("t.$column = :unitId")
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
                                $record['name'] ?? $record['id']
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
