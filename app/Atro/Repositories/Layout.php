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

use Atro\Core\Templates\Repositories\Base;
use Espo\Core\DataManager;
use Espo\ORM\Entity;

class Layout extends Base
{
    public function saveContent(Entity $entity, array $data): bool
    {
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
        }

        try {
            switch ($entity->get('viewType')) {
                case 'list';
                case 'listSmall':
                    $listItems = $entity->get('listItems') ?? [];
                    $processedItems = [];

                    foreach ($data as $index => $item) {
                        $listItemEntity = null;
                        if (!empty($item['id'])) {
                            foreach ($listItems as $listItem) {
                                if ($listItem->get('id') === $item['id']) {
                                    $listItemEntity = $listItem;
                                    $processedItems[] = $listItem;
                                }
                            }
                        }
                        if (empty($listItemEntity)) {
                            $listItemEntity = $this->getEntityManager()->getRepository('LayoutListItem')->get();
                        }

                        $listItemEntity->set([
                            'layoutId'    => $entity->get('id'),
                            'name'        => $item['name'],
                            'link'        => $item['link'] ?? false,
                            'notSortable' => $item['notSortable'] ?? false,
                            'align'       => $item['align'] ?? null,
                            'width'       => $item['width'] ?? null,
                            'widthPx'     => $item['widthPx'] ?? null,
                            'sortOrder'   => $index,
                        ]);

                        $this->getEntityManager()->saveEntity($listItemEntity);
                    }

                    foreach ($listItems as $listItem) {
                        if (!in_array($listItem, $processedItems)) {
                            $this->getEntityManager()->removeEntity($listItem);
                        }
                    }
                    break;
                case 'detail':
                case 'detailSmall':
                    $sections = $entity->get('sections') ?? [];
                    $processedSections = [];

                    foreach ($data as $index => $item) {
                        if (empty($item['id'])) {
                            $section = $this->getEntityManager()->getRepository('LayoutSection')->get();
                        } else {
                            foreach ($sections as $s) {
                                if ($s->get('id') === $item['id']) {
                                    $section = $s;
                                    $processedSections[] = $section;
                                    break;
                                }
                            }
                            if (empty($section)) {
                                continue;
                            }
                        }
                        $section->set([
                            'layoutId'            => $entity->get('id'),
                            'name'                => $item['customLabel'],
                            'style'               => $item['style'] ?? null,
                            'dynamicLogicVisible' => $item['dynamicLogicVisible'] ?? null,
                            'sortOrder'           => $index,
                        ]);
                        $this->getEntityManager()->saveEntity($section);


                        //create row items
                        $rowItems = $section->get('rowItems') ?? [];
                        $processedItems = [];
                        foreach ($item['rows'] as $rowIndex => $row) {
                            foreach ($row as $columnIndex => $column) {
                                if (!empty($column)) {
                                    $rowItemEntity = null;
                                    foreach ($rowItems as $rowItem) {
                                        if ($rowItem->get('id') === $column['id']) {
                                            $rowItemEntity = $rowItem;
                                            $processedItems[] = $rowItem;
                                            break;
                                        }
                                    }
                                    // create new entity
                                    if (empty($rowItemEntity)) {
                                        $rowItemEntity = $this->getEntityManager()->getRepository('LayoutRowItem')->get();
                                    }
                                    $rowItemEntity->set([
                                        'sectionId'   => $section->get('id'),
                                        'name'        => $column['name'] ?? '',
                                        'rowIndex'    => $rowIndex,
                                        'columnIndex' => $columnIndex,
                                        'fullWidth'   => $column['fullWidth'] ?? false,
                                    ]);
                                    $this->getEntityManager()->saveEntity($rowItemEntity);
                                }
                            }
                        }

                        foreach ($rowItems as $rowItem) {
                            if (!in_array($rowItem, $processedItems)) {
                                // delete item
                                $this->getEntityManager()->removeEntity($rowItem);
                            }
                        }
                    }

                    foreach ($sections as $section) {
                        if (!in_array($section, $processedSections)) {
                            // delete section
                            $this->getEntityManager()->removeEntity($section);
                        }
                    }
                    break;
                case 'relationships':
                    $relationshipItems = $entity->get('relationshipItems') ?? [];
                    $processedRelationships = [];

                    foreach ($data as $index => $item) {
                        $relationshipItemEntity = null;
                        if (!empty($item['id'])) {
                            foreach ($relationshipItems as $relationshipItem) {
                                if ($relationshipItem->get('id') === $item['id']) {
                                    $relationshipItemEntity = $relationshipItem;
                                    $processedRelationships[] = $relationshipItem;
                                }
                            }
                        }
                        if (empty($relationshipItemEntity)) {
                            $relationshipItemEntity = $this->getEntityManager()->getRepository('LayoutRelationshipItem')->get();
                        }

                        $relationshipItemEntity->set([
                            'layoutId'         => $entity->get('id'),
                            'name'             => $item['name'],
                            'style'            => $item['style'] ?? '',
                            'hiddenPerDefault' => $item['hiddenPerDefault'] ?? false,
                            'sortOrder'        => $index,
                        ]);

                        $this->getEntityManager()->saveEntity($relationshipItemEntity);
                    }

                    foreach ($relationshipItems as $relationshipItem) {
                        if (!in_array($relationshipItem, $processedRelationships)) {
                            $this->getEntityManager()->removeEntity($relationshipItem);
                        }
                    }
                    break;
                case 'sidePanelsDetail':
                case 'sidePanelsEdit':
                case 'sidePanelsDetailSmall':
                case 'sidePanelsEditSmall':
                    break;
            }

            if ($this->getEntityManager()->getPDO()->inTransaction()) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($this->getEntityManager()->getPDO()->inTransaction()) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            return false;
        }
        return true;
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        switch ($entity->get('viewType')) {
            case 'list';
            case 'listSmall':
                $this->getEntityManager()->getRepository('LayoutListItem')
                    ->where(['layoutId' => $entity->get('id')])
                    ->removeCollection();
                break;
            case 'detail':
            case 'detailSmall':
                $this->getEntityManager()->getRepository('LayoutSection')
                    ->where(['layoutId' => $entity->get('id')])
                    ->removeCollection();
                break;
            case 'relationships':
                $this->getEntityManager()->getRepository('LayoutRelationshipItem')
                    ->where(['layoutId' => $entity->get('id')])
                    ->removeCollection();
                break;
            case 'sidePanelsDetail':
            case 'sidePanelsEdit':
            case 'sidePanelsDetailSmall':
            case 'sidePanelsEditSmall':
                $this->getEntityManager()->getRepository('LayoutSidePanelItem')
                    ->where(['layoutId' => $entity->get('id')])
                    ->removeCollection();
                break;
        }
    }
}
