<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Templates\Services;

use Espo\ORM\Entity;
use Espo\Services\Record;

class Relation extends Record
{
    protected function afterCreateEntity(Entity $entity, $data)
    {
        parent::afterCreateEntity($entity, $data);

        $this->createHierarchical($entity);
    }

    public function createHierarchical(Entity $entity): void
    {
        $link = $this->getRepository()->getHierarchicalRelation();
        if (empty($link)) {
            return;
        }

        $hierarchicalEntity = $entity->get($link);
        if (empty($hierarchicalEntity)) {
            return;
        }

        $children = $this->getEntityManager()->getRepository($hierarchicalEntity->getEntityType())->getChildrenRecursivelyArray($hierarchicalEntity->get('id'));
        if (empty($children)) {
            return;
        }

        $additionalFields = $this->getRepository()->getAdditionalFieldsNames();

        foreach ($children as $childId) {
            $input = new \stdClass();
            foreach ($this->getRepository()->getRelationFields() as $relField) {
                $input->{"{$relField}Id"} = $relField === $link ? $childId : $entity->get("{$relField}Id");
            }
            foreach ($additionalFields as $additionalField) {
                $input->{$additionalField} = $entity->get($additionalField);
            }
            $parentId = $this->getPseudoTransactionManager()->pushCreateEntityJob($entity->getEntityType(), $input);
            $this->getPseudoTransactionManager()->pushUpdateEntityJob($hierarchicalEntity->getEntityType(), $hierarchicalEntity->get('id'), null, $parentId);
        }
    }
}
