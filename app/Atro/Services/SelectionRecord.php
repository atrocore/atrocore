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

namespace Atro\Services;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class SelectionRecord extends Base
{
  protected $mandatorySelectAttributeList = ['entityId', 'entityType'];

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $entityIds = [];
        foreach ($collection as $entity) {
            $entityIds[$entity->get('entityType')][] = $entity;
        }

        foreach ($entityIds as $entityType => $records) {
            $ids = array_map(fn($entity) => $entity->get('entityId'), $records);
            $entities = $this->getEntityManager()->getRepository($entityType)->where(['id' => $ids])->find();
            foreach ($entities as $entity) {
               if($this->getMetadata()->get(['scopes', $entityType, 'hasAttribute'])) {
                   $this->getInjection(AttributeFieldConverter::class)->putAttributesToEntity($entity);
               }
               foreach ($records as $record) {
                   if($record->get('entityId') === $entity->get('id')) {
                       $record->set('name', $entity->get('name'));
                       $record->set('entity', $entity->toArray());
                   }
               }
            }
        }
    }

    public function loadSelectionRecordDetails(Entity $entity): void
    {

    }
}
