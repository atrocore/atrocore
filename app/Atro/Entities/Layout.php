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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Base;
use Espo\ORM\EntityCollection;

class Layout extends Base
{
    protected $entityType = "Layout";

    protected function getKeyList(array $keys, bool $withIds): array
    {
        return $withIds ? array_merge(['id'], $keys) : $keys;
    }

    public function getData(bool $withIds = true): array
    {
        switch ($this->get('viewType')) {
            case 'list';
            case 'listSmall':
            case 'kanban':
                /* @var $listItems EntityCollection */
                $listItems = $this->get('listItems');
                $listItems = empty($listItems) ? [] : $listItems->toArray();
                usort($listItems, function ($a, $b) {
                    return $a['sortOrder'] <=> $b['sortOrder'];
                });
                $data = [];
                foreach ($listItems as $item) {
                    $newItem = [];
                    $keys = ['name', 'link', 'align', 'width', 'widthPx', 'notSortable'];
                    if ($this->get('viewType') === 'kanban') {
                        $keys = ['name', 'link', 'align', 'width', 'isLarge', 'cssStyle'];
                    }
                    $keys = $this->getKeyList($keys, $withIds);
                    foreach ($keys as $key) {
                        if (!empty($item[$key])) {
                            $newItem[$key] = $item[$key];
                        }
                    }
                    $data[] = $newItem;
                }
                return $data;

            case 'detail':
            case 'detailSmall':
                $data = [];
                foreach ($this->get('sections', ['orderBy' => 'sortOrder']) ?? [] as $section) {
                    $sectionData = ['label' => $section->get('name') ?? ''];
                    foreach ($this->getKeyList(['style'], $withIds) as $key) {
                        if (!empty($section->get($key))) {
                            $sectionData[$key] = $section->get($key);
                        }
                    }

                    $rowItems = $section->get('rowItems', ['orderBy' => ['rowIndex', 'columnIndex']]);
                    $grid = [];
                    foreach (empty($rowItems) ? [] : $rowItems->toArray() as $index => $item) {
                        $newItem = [];
                        foreach ($this->getKeyList(['name', 'fullWidth'], $withIds) as $key) {
                            if (!empty($item[$key])) {
                                $newItem[$key] = $item[$key];
                            }
                        }
                        $grid[$item['rowIndex']][$item['columnIndex']] = $newItem;
                    }

                    ksort($grid);
                    $sectionData['rows'] = [];
                    foreach ($grid as $index => $row) {
                        $newRow = [];
                        ksort($row);
                        foreach ($row as $item) {
                            $newRow[] = $item;
                        }
                        if (count($row) === 1) {
                            if (!isset($row[0])) {
                                array_unshift($newRow, false);
                            } else if (empty($row[0]['fullWidth'])) {
                                $newRow[] = false;
                            }
                        }
                        $sectionData['rows'][] = $newRow;
                    }

                    $data[] = $sectionData;
                }
                return $data;

            case 'relationships':
                /* @var $listItems EntityCollection */
                $relationshipItems = $this->get('relationshipItems');
                $relationshipItems = empty($relationshipItems) ? [] : $relationshipItems->toArray();
                usort($relationshipItems, function ($a, $b) {
                    return $a['sortOrder'] <=> $b['sortOrder'];
                });
                $data = [];
                foreach ($relationshipItems as $item) {
                    $newItem = [];
                    foreach ($this->getKeyList(['name', 'style', 'hiddenPerDefault'], $withIds) as $key) {
                        if (!empty($item[$key])) {
                            $newItem[$key] = $item[$key];
                        }
                    }
                    $data[] = $newItem;
                }
                return $data;

            case 'sidePanelsDetail':
            case 'sidePanelsEdit':
            case 'sidePanelsDetailSmall':
            case 'sidePanelsEditSmall':
                $sidePanelItems = $this->get('sidePanelItems');
                $sidePanelItems = empty($sidePanelItems) ? [] : $sidePanelItems->toArray();
                usort($sidePanelItems, function ($a, $b) {
                    return $a['sortOrder'] <=> $b['sortOrder'];
                });
                $data = [];
                foreach ($sidePanelItems as $item) {
                    $newItem = [];
                    foreach ($this->getKeyList(['style', 'hiddenPerDefault', 'disabled'], $withIds) as $key) {
                        if (!empty($item[$key])) {
                            $newItem[$key] = $item[$key];
                        }
                    }
                    $data[$item['name']] = $newItem;
                }
                return $data;
        }

        return [];
    }


}
