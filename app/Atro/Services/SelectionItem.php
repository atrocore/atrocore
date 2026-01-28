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
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class SelectionItem extends Base
{
    protected $mandatorySelectAttributeList = ['name', 'entityId', 'entityName'];

    protected array $services = [];

    public function replaceItem(string $id, \stdClass $selectedItem): bool
    {
        $item = $this->getRepository()->get($id);
        if ($item->get('entityName') !== $selectedItem->entityName) {
            throw new BadRequest('entityName mismatch');
        }
        $item->set('entityId', $selectedItem->entityId);
        $this->getEntityManager()->saveEntity($item);
        return true;
    }

    public function putAclMetaForLink(Entity $entityFrom, string $link, Entity $entity): void
    {
        if ($entityFrom->getEntityName() !== 'Selection' || $link !== 'selectionItems') {
            parent::putAclMetaForLink($entityFrom, $link, $entity);
            return;
        }

        $this->putAclMeta($entity);

        if ($this->getUser()->isAdmin()) {
            $entity->setMetaPermission('replaceItem', true);
            $entity->setMetaPermission('unlink', true);
            $entity->setMetaPermission('delete', true);
            return;
        }

        $entity->setMetaPermission('replaceItem', $this->getAcl()->check($entity, 'edit'));
        $entity->setMetaPermission('unlink', $this->getAcl()->check($entity, 'delete'));
        $entity->setMetaPermission('delete', false);

        if (!empty($record = $this->getEntityManager()->getEntity($entity->get('entityType'), $entity->get('entityId')))) {
            $entity->setMetaPermission('delete', $this->getAcl()->check($record, 'delete'));
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $entityIds = [];
        foreach ($collection as $key => $entity) {
            $entityIds[$entity->get('entityName')][$key] = $entity;
        }

        $loadEntity = !empty($selectParams['select']) && in_array('entity', $selectParams['select']);

        foreach ($entityIds as $entityType => $records) {
            $ids = array_map(fn($entity) => $entity->get('entityId'), $records);
            if ($loadEntity) {
                $entities = $this->getEntityManager()->getRepository($entityType)->where(['id' => $ids])->find();

                foreach ($records as $record) {
                    foreach ($entities as $entity) {
                        if ($this->getMetadata()->get(['scopes', $entityType, 'hasAttribute'])) {
                            $this->getInjection(AttributeFieldConverter::class)->putAttributesToEntity($entity);
                            $this->getService($entityType)->prepareEntityForOutput($entity);
                        }

                        if ($record->get('entityId') === $entity->get('id')) {
                            $record->set('recordId', $entity->get('id'));
                            $record->set('recordName', $entity->get('name') ?? $entity->get('id'));
                            $record->set('entity', $entity->toArray());
                        }
                    }
                }
            } else {
                $select = ['id'];

                if ($this->getMetadata()->get(['entityDefs', $entityType, 'fields', 'name'])) {
                    $select[] = 'name';
                }

                $entities = $this->getEntityManager()->getRepository($entityType)->select($select)->where(['id' => $ids])->find();

                foreach ($records as $record) {
                    foreach ($entities as $entity) {
                        if ($record->get('entityId') === $entity->get('id')) {
                            $record->set('recordId', $entity->get('id'));
                            $record->set('recordName', $entity->get('name') ?? $entity->get('id'));
                        }
                    }
                }
            }
        }
    }

    public function createOnCurrentItem(string $entityName, string $entityId): bool
    {
        $currentSelection = $this->getUser()->get('currentSelection');

        $masterEntity = $this->getMetadata()->get(['scopes', $entityName, 'primaryEntityId']);

        if (empty($currentSelection)) {
            $currentSelection = $this->getEntityManager()->getEntity('Selection');
            $currentSelection->set('type', 'single');
            $currentSelection->set('entity', $entityName);
            if (!empty($masterEntity)) {
                $currentSelection->set('entity', $masterEntity);
            }
            $this->getEntityManager()->saveEntity($currentSelection);

            $this->getUser()->set('currentSelectionId', $currentSelection->get('id'));

            $this->getEntityManager()->saveEntity($this->getUser());
        }

        $relevantEntities = [$entityName];

        if (!empty($masterEntity)) {
            $relevantEntities[] = $masterEntity;
        }

        if ($currentSelection->get('type') === 'single' && !in_array($currentSelection->get('entity'), $relevantEntities)) {
            $currentSelection->set('type', 'multiple');
            $currentSelection->set('entity', null);
            $this->getEntityManager()->saveEntity($currentSelection);
        }

        $record = $this->getEntityManager()->getEntity('SelectionItem');
        $record->set('entityId', $entityId);
        $record->set('entityName', $entityName);
        $record->set('selectionId', $currentSelection->get('id'));

        try {
            $this->getEntityManager()->saveEntity($record);
        } catch (NotUnique $e) {
        }

        return true;
    }

    protected function getService(string $name): Record
    {
        if (!empty($this->services[$name])) {
            return $this->services[$name];
        }
        return $this->services[$name] = $this->getServiceFactory()->create($name);
    }
}
