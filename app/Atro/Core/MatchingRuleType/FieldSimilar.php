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

class FieldSimilar extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [
            "markdown",
            "text",
            "url",
            "varchar",
            "wysiwyg",
            "array",
            "multiEnum"
        ];
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
                sort($stageValue);
                sort($masterValue);

                return $stageValue === $masterValue ? $this->getWeight() : 0.0;
            }

            if ($stageValue === null && $masterValue === null) {
                return 0.0;
            }

            $stageValue  = str_replace(' ', '', strtolower(trim((string)$stageValue)));
            $masterValue = str_replace(' ', '', strtolower(trim((string)$masterValue)));

            return $stageValue === $masterValue ? $this->getWeight() : 0.0;
        }

        $field = $this->settings->get('field');
        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$field}.type");

        if (in_array($fieldType, ['array', 'linkMultiple', 'multiEnum'])) {
            $stageValue = $fieldType === 'linkMultiple'
                ? ($stageEntity->get($field . 'Ids') ?? [])
                : ($stageEntity->get($field) ?? []);
            $masterValue = $masterEntityData[$field . 'Ids'] ?? $masterEntityData[$field] ?? [];

            if (is_string($masterValue)) {
                $masterValue = json_decode($masterValue, true) ?? [];
            }

            if (!is_array($stageValue) || !is_array($masterValue)) {
                return 0.0;
            }

            sort($stageValue);
            sort($masterValue);

            return $stageValue === $masterValue ? $this->getWeight() : 0.0;
        }

        if ($stageEntity->get($field) === null && ($masterEntityData[$field] ?? null) === null) {
            return 0.0;
        }

        $stageValue = str_replace(' ', '', strtolower(trim((string)$stageEntity->get($field))));
        $masterValue = str_replace(' ', '', strtolower(trim((string)$masterEntityData[$field])));

        return $stageValue === $masterValue ? $this->getWeight() : 0.0;
    }
}
