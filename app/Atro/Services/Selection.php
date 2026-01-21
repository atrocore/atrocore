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
use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;

class Selection extends Base
{
    protected $mandatorySelectAttributeList = ['number', 'entity', 'entityTypes', 'type'];

    public function createSelectionWithRecords(string $scope, array $entityIds)
    {
        $selection = $this->getEntityManager()->getEntity('Selection');
        $selection->set('type', 'single');
        $selection->set('entity', $scope);
        if(!empty($masterEntity = $this->getMetadata()->get(['scopes', $scope, 'primaryEntityId']))) {
            $selection->set('entity', $masterEntity);
        }
        $this->getEntityManager()->saveEntity($selection);

        foreach ($entityIds as $entityId) {
            $record = $this->getEntityManager()->getEntity('SelectionItem');
            $record->set('entityId', $entityId);
            $record->set('entityType', $scope);
            $record->set('selectionId', $selection->get('id'));
            $this->getEntityManager()->saveEntity($record);
        }

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
}