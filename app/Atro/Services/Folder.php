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

use Atro\Core\Templates\Services\Hierarchy;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Folder extends Hierarchy
{
    public function findLinkedEntities($id, $link, $params)
    {
        if ($link === 'parents' || $link === 'children' || $link === 'files') {
            $params['where'][] = [
                'type'  => 'bool',
                'value' => ['hiddenAndUnHidden']
            ];
        }

        return parent::findLinkedEntities($id, $link, $params);
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        foreach ($collection as $entity) {
            $entity->_pathPrepared = true;
            $entity->set('folderPath', $this->getRepository()->getFolderHierarchyData($entity->get('id')));
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_pathPrepared)) {
            $folderPath = [];

            $current = clone $entity;
            while (!empty($parent = $current->getParent())) {
                $folderPath[] = [
                    'id'         => $current->get('id'),
                    'name'       => $current->get('name'),
                    'parentId'   => $parent->get('id'),
                    'parentName' => $parent->get('name'),
                ];
                $current = $parent;
            }

            $entity->set('folderPath', $folderPath);
        }
    }
}
