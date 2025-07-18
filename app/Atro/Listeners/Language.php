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

class Language extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        foreach ($data as $locale => $rows) {
            foreach ($this->getMetadata()->get('scopes', []) as $scope => $scopeDefs) {
                if (str_starts_with($scope, 'UserFollowed')) {
                    $relScope = str_replace('UserFollowed', '', $scope);
                    if (empty($data[$locale]['Global']['scopeNames'][$scope])) {
                        $data[$locale]['Global']['scopeNames'][$scope] = $this->getLabel($data, $locale, 'Global', 'UserFollowed', 'labels') .
                            " " . $this->getLabel($data, $locale, 'Global', $relScope, 'scopeNames');
                    }
                    if (empty($data[$locale]['Global']['scopeNamesPlural'][$scope])) {
                        $data[$locale]['Global']['scopeNamesPlural'][$scope] = $this->getLabel($data, $locale, 'Global', 'UserFollowed', 'labels') .
                            " " . $this->getLabel($data, $locale, 'Global', $relScope, 'scopeNamesPlural');
                    }
                }
            }
        }

        foreach ($data as $locale => $rows) {
            foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
                if (empty($entityDefs['fields'])) {
                    continue 1;
                }

                // Associations
                $associateEntity = $this->getMetadata()->get(['scopes', $entity, 'associatesForEntity']);
                if (!empty($associateEntity)) {
                    if (!isset($data[$locale][$entity]['fields']["associatingItem"])) {
                        $data[$locale][$entity]['fields']["associatingItem"] = $this->getLabel($data, $locale, 'Global', 'associatingItem', 'labels');
                    }

                    if (!isset($data[$locale][$entity]['fields']["associatedItem"])) {
                        $data[$locale][$entity]['fields']["associatedItem"] = $this->getLabel($data, $locale, 'Global', 'associatedItem', 'labels');
                    }

                    if (!isset($data[$locale][$entity]['fields']["associatedItems"])) {
                        $data[$locale][$entity]['fields']["associatedItems"] = $this->getLabel($data, $locale, 'Global', 'associatedItems', 'labels');
                    }

                    if (!isset($data[$locale][$entity]['tooltips']["association"])) {
                        $data[$locale][$entity]['tooltips']["association"] = $this->getLabel($data, $locale, 'Global', 'associationTooltip', 'labels');
                    }

                    if (!isset($data[$locale][$associateEntity]['fields']["associatedItems"])) {
                        $data[$locale][$associateEntity]['fields']["associatedItems"] = $this->getLabel($data, $locale, 'Global', 'associatedItems', 'labels');
                    }

                    if (!isset($data[$locale][$associateEntity]['fields']["associatingItems"])) {
                        $data[$locale][$associateEntity]['fields']["associatingItems"] = $this->getLabel($data, $locale, 'Global', 'associatingItems', 'labels');
                    }

                    if (!isset($data[$locale][$associateEntity]['fields']["associatedItemRelations"])) {
                        $data[$locale][$associateEntity]['fields']["associatedItemRelations"] = $this->getLabel($data, $locale, 'Global', 'associatedItems', 'labels') . " (" . $this->getLabel($data, $locale, 'Global', 'Relation') . ")";
                    }

                    if (!isset($data[$locale][$associateEntity]['fields']["associatingItemRelations"])) {
                        $data[$locale][$associateEntity]['fields']["associatingItemRelations"] = $this->getLabel($data, $locale, 'Global', 'associatingItems', 'labels') . " (" . $this->getLabel($data, $locale, 'Global', 'Relation') . ")";
                    }

                    if (!isset($data[$locale]['Global']['scopeNames'][$entity])) {
                        $data[$locale]['Global']['scopeNames'][$entity] = $this->getLabel($data, $locale, 'Global', 'Associated', 'labels') . ' ' . $this->getLabel($data, $locale, 'Global', $associateEntity, 'scopeNames');
                    }
                }

                foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                    if (empty($fieldDefs['type'])) {
                        continue;
                    }

                    if (!empty($fieldDefs['linkToRelationEntity']) && empty($data[$locale][$entity]['fields'][$field])) {
                        $fieldLabel = $this->getLabel($data, $locale, 'Global', $fieldDefs['linkToRelationEntity'], 'scopeNamesPlural');
                        $relationEntity = $this->getMetadata()->get(['entityDefs', $entity, 'links', $field, 'entity']);
                        $fieldLabel1 = $this->getLabel($data, $locale, 'Global', $relationEntity, 'scopeNames');

                        $data[$locale][$entity]['fields'][$field] = "$fieldLabel ($fieldLabel1)";
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

        $languages = [];
        $mainLanguageCode = $this->getConfig()->get('mainLanguage');
        $mainLanguageName = null;

        foreach ($this->getConfig()->get('referenceData.Language', []) as $item) {
            if ($item['code'] === $mainLanguageCode) {
                $mainLanguageName = $item['name'];
                continue;
            }
            $languages[$item['code']] = $item['name'];
        }

        if (!empty($languages)) {
            foreach ($data as $locale => $rows) {
                foreach ($rows as $scope => $items) {
                    // add name translation if field exists
                    if (empty($items['fields']['name']) && !empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', 'name']))) {
                        $items['fields']['name'] = $data[$locale][$scope]['fields']['name'] = $data[$locale]['Global']['fields']['name'] ?? 'Name';
                    }
                    foreach (['fields', 'tooltips'] as $type) {
                        if (isset($items[$type])) {
                            foreach ($items[$type] as $field => $value) {
                                if ($scope !== 'Global') {
                                    if (empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', $field, 'isMultilang']))) {
                                        continue;
                                    }
                                    if (array_key_exists($locale, $languages) && !empty($mainLanguageName)) {
                                        $data[$locale][$scope][$type][$field] = $value . ' / ' . $mainLanguageName;
                                    }
                                }

                                foreach ($languages as $code => $name) {
                                    $mField = $field . ucfirst(Util::toCamelCase(strtolower($code)));
                                    if (!isset($data[$locale][$scope][$type][$mField])) {
                                        if ($type == 'fields' && $code !== $locale) {
                                            $data[$locale][$scope][$type][$mField] = $value . ' / ' . $name;
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
