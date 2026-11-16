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

class FieldEqual extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [
            "array",
            "bool",
            "date",
            "datetime",
            "enum",
            "file",
            "float",
            "int",
            "link",
            "measure",
            "multiEnum",
            "markdown",
            "password",
            "text",
            "url",
            "varchar",
            "wysiwyg",
            "email"
        ];
    }

    public function match(Entity $stageEntity, array $masterEntityData): float
    {
        if (!empty($this->settings->get('attributeId'))) {
            $attributeId = $this->settings->get('attributeId');
            $entityName  = $stageEntity->getEntityName();
            $stageValue  = $this->loadAttributeRawValue($entityName, $stageEntity->id, $attributeId);
            $masterValue = $this->loadAttributeRawValue($this->rule->getMatching()?->get('masterEntity') ?? $entityName, $masterEntityData['id'], $attributeId);

            if ($stageValue === false || $masterValue === false) {
                return 0.0;
            }

            if ($stageValue === null && $masterValue === null) {
                return 0.0;
            }

            return $stageValue === $masterValue ? $this->getWeight() : 0.0;
        }

        $fieldName = $this->settings->get('field');

        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$fieldName}.type");

        if (in_array($fieldType, ['link', 'file'])) {
            $fieldName .= 'Id';
        }

        $value = $stageEntity->get($fieldName);
        if (in_array($fieldType, ['array', 'multiEnum'])) {
            if ($value !== null) {
                $value = json_encode($value);
            }
        }

        if ($value === null && ($masterEntityData[$fieldName] ?? null) === null) {
            return 0.0;
        }

        if ($value === $masterEntityData[$fieldName]) {
            return $this->getWeight();
        }

        return 0.0;
    }
}
