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

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Core\Templates\Repositories\Relation;
use Atro\Core\Utils\Util;
use Espo\Core\Templates\Services\Relationship;

class Language extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        foreach ($data as $locale => $rows) {
            foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
                if (empty($entityDefs['fields'])) {
                    continue 1;
                }
                foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                    if (empty($fieldDefs['type'])) {
                        continue;
                    }

                    // add translate for relation virtual field
                    if (!isset($data[$locale][$entity]['fields'][$field]) && !empty($relData = Relation::isVirtualRelationField($field))) {
                        $data[$locale][$entity]['fields'][$field] = $data[$locale][$relData['relationName']]['fields'][$relData['fieldName']] ?? $relData['fieldName'];
                    }

                    switch ($fieldDefs['type']) {
                        case 'link':
                            if (!isset($data[$locale][$entity]['fields'][$field])) {
                                $entityType = $this->getMetadata()->get(['entityDefs', $entity, 'links', $field, 'entity']);
                                if (isset($data[$locale]['Global']['fields'][$field])) {
                                    $data[$locale][$entity]['fields'][$field] = $data[$locale]['Global']['fields'][$field];
                                } else if (isset($data[$locale]['Global']['scopeNames'][$entityType])) {
                                    $data[$locale][$entity]['fields'][$field] = $data[$locale]['Global']['scopeNames'][$entityType];
                                }
                            }
                            break;
                        case 'rangeInt':
                        case 'rangeFloat':
                            $fieldLabel = !empty($rows[$entity]['fields'][$field]) ? $rows[$entity]['fields'][$field] : $field;
                            $fromLabel = !empty($rows['Global']['labels']['From']) ? $rows['Global']['labels']['From'] : 'From';
                            $toLabel = !empty($rows['Global']['labels']['To']) ? $rows['Global']['labels']['To'] : 'To';
                            $data[$locale][$entity]['fields'][$field . 'From'] = $fieldLabel . ' ' . $fromLabel;
                            $data[$locale][$entity]['fields'][$field . 'To'] = $fieldLabel . ' ' . $toLabel;

                            if (!empty($fieldDefs['unitField'])) {
                                $fieldType = $fieldDefs['type'] === 'rangeInt' ? 'int' : 'float';
                                $typeLabel = !empty($rows['Global']['labels'][$fieldType . 'Part']) ? $rows['Global']['labels'][$fieldType . 'Part'] : "({$fieldType})";
                                $data[$locale][$entity]['fields'][$field . 'From'] .= ' ' . $typeLabel;
                                $data[$locale][$entity]['fields'][$field . 'To'] .= ' ' . $typeLabel;
                            }
                            break;
                    }

                    if (!empty($fieldDefs['relationshipFilterField']) && empty($data[$locale][$entity]['fields'][$field])) {
                        if (!empty($data[$locale]['Global']['scopeNamesPlural'][$fieldDefs['entity']])) {
                            $filterEntity = $data[$locale]['Global']['scopeNamesPlural'][$fieldDefs['entity']];
                        } elseif (!empty($data['en_US']['Global']['scopeNamesPlural'][$fieldDefs['entity']])) {
                            $filterEntity = $data['en_US']['Global']['scopeNamesPlural'][$fieldDefs['entity']];
                        } else {
                            $filterEntity = $fieldDefs['entity'];
                        }

                        $data[$locale][$entity]['fields'][$field] = $filterEntity;
                    }

                    if (!empty($fieldDefs['unitField'])) {
                        $mainField = $fieldDefs['mainField'] ?? $field;
                        $fieldLabel = $this->getLabel($data, $locale, $entity, $mainField);
                        $mainFieldType = $this->getMetadata()->get(['entityDefs', $entity, 'fields', $mainField, 'type']);

                        if (!in_array($fieldDefs['type'], ['rangeInt', 'rangeFloat'])) {
                            $data[$locale][$entity]['fields'][$mainField] = $fieldLabel . ' ' . $this->getLabel($data, $locale, $entity, $mainFieldType . 'Part', 'labels');
                            $data[$locale][$entity]['fields']['unit' . ucfirst($mainField)] = $fieldLabel;
                        }

                        $data[$locale][$entity]['fields'][$mainField . 'Unit'] = $fieldLabel . ' ' . $this->getLabel($data, $locale, $entity, 'unitPart', 'labels');
                    }
                }
            }
        }

        if (!empty($this->getConfig()->get('isMultilangActive')) && !empty($languages = $this->getConfig()->get('referenceData.Language', []))) {
            foreach ($data as $locale => $rows) {
                foreach ($rows as $scope => $items) {
                    foreach (['fields', 'tooltips'] as $type) {
                        if (isset($items[$type])) {
                            foreach ($items[$type] as $field => $value) {
                                foreach ($languages as $language) {
                                    if ($language['role'] !== 'additional') {
                                        continue;
                                    }
                                    $mField = $field . ucfirst(Util::toCamelCase(strtolower($language['code'])));
                                    if (!isset($data[$locale][$scope][$type][$mField])) {
                                        if ($type == 'fields') {
                                            $data[$locale][$scope][$type][$mField] = $value . ' / ' . $language['name'];
                                        } else {
                                            $data[$locale][$scope][$type][$mField] = $value;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $referenceData = $this->getConfig()->get('referenceData.Locale', []);
        if (!empty($referenceData)) {
            foreach ($data as $locale => $rows) {
                foreach ($referenceData as $item) {
                    $value = $this->getLabel($data, $locale, 'Admin', 'label') . ' / ' . $item['name'];
                    $data[$locale]['Admin']['fields'][Util::toCamelCase('label_' . strtolower($item['code']))] = $value;
                }
            }
        }

        $event->setArgument('data', $data);
    }

    protected function getLabel(array $data, string $locale, string $entityType, string $key, string $category = 'fields'): string
    {
        if (isset($data[$locale][$entityType][$category][$key])) {
            $fieldLabel = $data[$locale][$entityType][$category][$key];
        } elseif (isset($data[$locale]['Global'][$category][$key])) {
            $fieldLabel = $data[$locale]['Global'][$category][$key];
        } elseif (isset($data['en_US'][$entityType][$category][$key])) {
            $fieldLabel = $data['en_US'][$entityType][$category][$key];
        } elseif (isset($data['en_US']['Global'][$category][$key])) {
            $fieldLabel = $data['en_US']['Global'][$category][$key];
        } else {
            $fieldLabel = $key;
        }

        return $fieldLabel;
    }
}
