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

        $records = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('fh.parent_id, f1.name as parent_name, fh.entity_id, f.name as entity_name')
            ->from('folder_hierarchy', 'fh')
            ->innerJoin('fh', 'folder', 'f', 'f.id=fh.entity_id')
            ->innerJoin('fh', 'folder', 'f1', 'f1.id=fh.parent_id')
            ->where('fh.deleted=:false')
            ->andWhere('f.deleted=:false')
            ->andWhere('f1.deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $hierarchyData = [];
        foreach ($records as $record) {
            $hierarchyData[$record['entity_id']] = $record;
        }

        foreach ($collection as $entity) {
            $path = [];

            $id = $entity->get('id');
            while (true) {
                if (!isset($hierarchyData[$id]) || $hierarchyData[$id]['parent_id'] === $hierarchyData[$id]['entity_id']) {
                    break;
                }
                $path[] = [
                    'id'         => $hierarchyData[$id]['entity_id'],
                    'name'       => $hierarchyData[$id]['entity_name'],
                    'parentId'   => $hierarchyData[$id]['parent_id'],
                    'parentName' => $hierarchyData[$id]['parent_name'],
                ];
                $id = $hierarchyData[$id]['parent_id'];
            }

            $entity->_pathPrepared = true;
            $entity->set('folderPath', $path);
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_pathPrepared)) {
            $path = [];

            $current = clone $entity;
            while (!empty($parent = $current->getParent())) {
                $path[] = [
                    'id'         => $current->get('id'),
                    'name'       => $current->get('name'),
                    'parentId'   => $parent->get('id'),
                    'parentName' => $parent->get('name'),
                ];
                $current = $parent;
            }

            $entity->set('folderPath', $path);
        }
    }
}
