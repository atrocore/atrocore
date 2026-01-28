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

namespace Atro\Services;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Exceptions\Exception;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Cluster extends Base
{
    protected $mandatorySelectAttributeList = ['masterEntity', 'goldenRecordId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($record->_preparedInCollection) && !empty($entity->get('goldenRecordId'))) {
            $goldenRecord = $this->getEntityManager()->getEntity($entity->get('masterEntity'), $entity->get('goldenRecordId'));
            if (!empty($goldenRecord)) {
                $entity->set('goldenRecordName', $goldenRecord->get('name'));
            }
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $entityIds = [];
        foreach ($collection as $key => $entity) {
            $entityIds[$entity->get('masterEntity')][$key] = $entity;
        }

        foreach ($entityIds as $entityType => $records) {
            $ids = array_map(fn($entity) => $entity->get('goldenRecordId'), $records);

            $select = ['id'];
            $nameField = $this->getMetadata()->get(['scopes', $entityType, 'nameField']) ?? 'name';
            if ($this->getMetadata()->get(['entityDefs', $entityType, 'fields', $nameField])) {
                $select[] = $nameField;
            }

            $entities = $this->getEntityManager()->getRepository($entityType)->select($select)->where(['id' => $ids])->find();

            $retrievedIds = [];

            foreach ($entities as $entity) {
                $retrievedIds[] = $entity->get('id');
            }

            foreach ($records as $key => $record) {
                if (!$this->getAcl()->check($record, 'read')) {
                    unset($collection[$key]);
                }
                foreach ($entities as $entity) {
                    if ($record->get('goldenRecordId') === $entity->get('id')) {
                        $record->set('goldenRecordName', $entity->get($nameField) ?? $entity->get('id'));
                        $record->_preparedInCollection = true;
                    }
                }
            }
        }

        // we reset the index from 0 in case of unset
        $entities = [];
        foreach ($collection as $key => $record) {
            $entities[] = $record;
            $collection->offsetUnset($key);
        }

        foreach ($entities as $key => $entity) {
            $collection->offsetSet($key, $entity);
        }
    }
}
