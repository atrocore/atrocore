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

use Atro\Core\Templates\Services\ReferenceData;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class EntityField extends ReferenceData
{
    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        foreach ($collection as $entity) {
            $entity->_defaultNamePrepared = true;
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_defaultNamePrepared) && !empty($entity->get('default'))) {
            $foreignEntity = null;
            switch ($entity->get('type')) {
                case 'link':
                case 'linkMultiple':
                    $foreignEntity = $this
                        ->getMetadata()
                        ->get(['entityDefs', $entity->get('entityId'), 'links', $entity->get('code'), 'entity']);
                    break;
                case 'measure':
                    $foreignEntity = 'Unit';
                    break;
                case 'file':
                    $foreignEntity = 'File';
                    break;
                case 'extensibleEnum':
                case 'extensibleMultiEnum':
                    $foreignEntity = 'ExtensibleEnumOption';
                    break;
            }

            if (!empty($foreignEntity)) {
                $repository = $this->getEntityManager()->getRepository($foreignEntity);
                if (in_array($entity->get('type'), ['linkMultiple', 'extensibleMultiEnum'])) {
                    $defaultNames = [];
                    foreach ($repository->where(['id' => $entity->get('default')])->find() as $foreign) {
                        $defaultNames[$foreign->get('id')] = $foreign->get('name');
                    }
                    $entity->set('defaultNames', $defaultNames);
                } else {
                    if (!empty($foreign = $repository->get($entity->get('default')))) {
                        $entity->set('defaultName', $foreign->get('name'));
                    }
                }
            }
        }
    }
}
