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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Selection extends Base
{
    protected $mandatorySelectAttributeList = ['number', 'entity', 'entityTypes', 'type'];

    public function createSelectionWithRecords(string $entityName, array $entityIds): Entity
    {
        if (!$this->getAcl()->check('Selection', 'create')) {
            throw new Forbidden();
        }

        $selection = $this->createSelection($entityName);

        $items = [];
        foreach ($entityIds as $entityId) {
            $record = $this->getEntityManager()->getEntity('SelectionItem');
            $record->set('entityId', $entityId);
            $record->set('entityName', $entityName);
            $record->set('selectionId', $selection->get('id'));
            $this->getEntityManager()->saveEntity($record);

            $items[] = $record->getValueMap();
        }

        $selection->set('selectionItems', $items);

        return $selection;
    }

    public function addRecordsToSelection(string $selectionId, string $entityName, array $entityIds): Entity
    {
        $selection = $this->getEntityManager()->getEntity('Selection', $selectionId);
        if (empty($selection)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($selection, 'edit')) {
            throw new Forbidden();
        }

        if ($selection->get('type') === 'single' && $selection->get('entity') !== $entityName) {
            throw new BadRequest(
                sprintf($this->getLanguage()->translate('entityTypeMismatch', 'messages', 'Selection'), $selection->get('entity'))
            );
        }

        $exists = $this
            ->getEntityManager()
            ->getRepository('SelectionItem')
            ->select(['entityId'])
            ->where([
                'selectionId' => $selectionId,
                'entityId'    => $entityIds,
            ])
            ->find();
        $exists = array_column($exists->toArray(), 'entityId');

        $items = [];
        foreach ($entityIds as $entityId) {
            if (in_array($entityId, $exists)) {
                continue;
            }

            $record = $this->getEntityManager()->getEntity('SelectionItem');
            $record->set('entityId', $entityId);
            $record->set('entityName', $entityName);
            $record->set('selectionId', $selection->get('id'));
            $this->getEntityManager()->saveEntity($record);

            $items[] = $record->getValueMap();
        }

        $selection->set('selectionItems', $items);

        return $selection;
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        $loadEntities = !empty($selectParams['select']) && in_array('entityTypes', $selectParams['select']);
        foreach ($collection as $entity) {
            $entity->_loadEntity = $loadEntities;
        }

        parent::prepareCollectionForOutput($collection, $selectParams);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        if (!property_exists($entity, '_loadEntity') || !empty($entity->_loadEntity)) {
            $entityTypes = $this->getRepository()->getEntities($entity->id);
            $entity->set('entityTypes', $entityTypes);
            $entity->set('entityTypesCount', count($entityTypes));
        }

        parent::prepareEntityForOutput($entity);
    }

    protected function createSelection(string $entityName): Entity
    {
        $selection = $this->getEntityManager()->getEntity('Selection');
        $selection->set('type', 'single');
        $selection->set('entity', $entityName);
        if (!empty($masterEntity = $this->getMetadata()->get(['scopes', $entityName, 'primaryEntityId']))) {
            $selection->set('entity', $masterEntity);
        }
        $this->getEntityManager()->saveEntity($selection);
        return $selection;
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('language');
    }
}
