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

namespace Atro\Core\MatchingRuleType;

use Espo\ORM\Entity;

class FieldContains extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [
            "markdown",
            "password",
            "text",
            "url",
            "varchar",
            "wysiwyg",
            "array",
            "multiEnum"
        ];
    }

    public function isSymmetric(): bool
    {
        return false;
    }

    public function match(Entity $stageEntity, array $masterEntityData): float
    {
        if (!empty($this->settings->get('attributeId'))) {
            $attributeId = $this->settings->get('attributeId');
            $entityName  = $stageEntity->getEntityName();
            $stageValue  = $this->loadAttributeRawValue($entityName, $stageEntity->id, $attributeId);
            $masterValue = $this->loadAttributeRawValue($entityName, $masterEntityData['id'], $attributeId);

            if ($stageValue === false || $masterValue === false) {
                return 0.0;
            }

            $attribute = $this->getEntityManager()->getEntity('Attribute', $attributeId);
            if ($attribute && in_array($attribute->get('type'), ['array', 'multiEnum', 'linkMultiple'])) {
                $stageValue  = is_string($stageValue) ? (json_decode($stageValue, true) ?? []) : ($stageValue ?? []);
                $masterValue = is_string($masterValue) ? (json_decode($masterValue, true) ?? []) : ($masterValue ?? []);

                if (!is_array($stageValue) || !is_array($masterValue) || empty($stageValue) || empty($masterValue)) {
                    return 0.0;
                }

                return empty(array_diff($stageValue, $masterValue)) ? $this->getWeight() : 0.0;
            }

            if ((string)$stageValue === '' || (string)$masterValue === '') {
                return 0.0;
            }

            return str_contains((string)$masterValue, (string)$stageValue) ? $this->getWeight() : 0.0;
        }

        $field = $this->settings->get('field');

        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$field}.type");

        $stageValue = $stageEntity->get($field);
        $masterValue = $masterEntityData[$field] ?? null;

        if (in_array($fieldType, ['array', 'linkMultiple', 'multiEnum'])) {
            if ($fieldType === 'linkMultiple') {
                $stageValue  = $stageEntity->get($field . 'Ids') ?? [];
                $masterValue = $masterEntityData[$field . 'Ids'] ?? $masterEntityData[$field] ?? [];
            } elseif (is_string($masterValue)) {
                $masterValue = json_decode($masterValue, true) ?? [];
            }

            if (!is_array($stageValue) || !is_array($masterValue) || empty($stageValue) || empty($masterValue)) {
                return 0.0;
            }

            if (empty(array_diff($stageValue, $masterValue))) {
                return $this->getWeight();
            }
        } else {
            if ((string)$stageValue === '' || (string)$masterValue === '') {
                return 0.0;
            }

            if (str_contains((string)$masterValue, (string)$stageValue)) {
                return $this->getWeight();
            }
        }

        return 0.0;
    }
}
