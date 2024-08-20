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
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

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

    public function getPreparedUnit(string $measureId, string $unitId): array
    {
        $units = $this->getMeasureUnits($measureId);
        if (!isset($units[$unitId])) {
            return [];
        }
        $unit = $units[$unitId];
        $measure = $this->get($measureId);
        if (empty($measure)) {
            return [];
        }

        return [
            'displayFormat' => $measure->get('displayFormat'),
            'name'          => $unit->get('name'),
            'symbol'        => $unit->get('symbol')
        ];
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
                if (empty($fieldDef['notStorable']) && !empty($fieldDef['measureId']) && $fieldDef['measureId'] === $entity->get('id')) {
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
            $this->getEntityManager()->removeEntity($unit, ['skipIsMainValidation' => true]);
        }

        parent::afterRemove($entity, $options);
    }
}
