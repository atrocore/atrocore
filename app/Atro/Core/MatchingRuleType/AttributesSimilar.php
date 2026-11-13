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

namespace Atro\Core\MatchingRuleType;

use Atro\Repositories\Attribute as AttributeRepository;
use Espo\ORM\Entity;

class AttributesSimilar extends FieldsSimilar
{
    protected const array EXCLUDED_ATTRIBUTE_TYPES = ['composite', 'script', 'linkMultiple'];

    protected array $excludedAttributeIds = [];

    protected ?string $stageEntityId = null;

    protected array $stageAttributeValues = [];

    public function getComparedFields(): array
    {
        return [];
    }

    public function getComparedAttributes(): array
    {
        $matching = $this->rule->getMatching();
        if (empty($matching)) {
            return [];
        }

        $entityIds = [];
        foreach ([$matching->get('entity'), $matching->get('masterEntity')] as $entityName) {
            $entityIds[] = $this->getMetadata()->get("scopes.{$entityName}.primaryEntityId") ?? $entityName;
        }

        $attributeIds = [];
        foreach ($this->getEntityManager()->getRepository('Attribute')->where(['entityId' => array_unique($entityIds)])->find() as $attribute) {
            if (!in_array($attribute->get('type'), self::EXCLUDED_ATTRIBUTE_TYPES) && !in_array($attribute->id, $this->getExcludedAttributeIds())) {
                $attributeIds[] = $attribute->id;
            }
        }

        return $attributeIds;
    }

    public function match(Entity $stageEntity, array $masterEntityData): float
    {
        $matching = $this->rule->getMatching();
        if (empty($matching)) {
            return 0.0;
        }

        if ($this->stageEntityId !== $stageEntity->id) {
            $this->stageEntityId = $stageEntity->id;
            $this->stageAttributeValues = $this->getAttributeRepository()->getAttributeValues($stageEntity->getEntityName(), $stageEntity->id);
        }

        $stageValues = $this->stageAttributeValues;
        $masterValues = $this->getAttributeRepository()->getAttributeValues($matching->get('masterEntity'), $masterEntityData['id']);

        $similarity = 0.0;
        $count = 0;

        foreach (array_keys($stageValues + $masterValues) as $attributeId) {
            $stageRow = $stageValues[$attributeId] ?? null;
            $masterRow = $masterValues[$attributeId] ?? null;
            $type = ($stageRow ?? $masterRow)['attribute_type'];

            if (
                in_array($type, self::EXCLUDED_ATTRIBUTE_TYPES)
                || in_array($attributeId, $this->getExcludedAttributeIds())
                || (($stageRow === null || $masterRow === null) && !empty($this->settings->get('ignoreUnavailableAttributes')))
            ) {
                continue;
            }

            $column = $this->getAttributeValueColumn($type);

            switch (self::SIMILARITY_TYPES[$type] ?? null) {
                case 'text':
                    foreach (array_keys($stageRow ?? $masterRow) as $key) {
                        if ($key !== $column && !str_starts_with($key, $column . '_')) {
                            continue;
                        }
                        $stageTrigrams = $this->getTrigrams((string)($stageRow[$key] ?? null), $type);
                        $masterTrigrams = $this->getTrigrams((string)($masterRow[$key] ?? null), $type);
                        if (empty($stageTrigrams) && empty($masterTrigrams)) {
                            continue;
                        }
                        $similarity += 2 * count(array_intersect_key($stageTrigrams, $masterTrigrams)) / (count($stageTrigrams) + count($masterTrigrams));
                        $count++;
                    }
                    break;
                case 'list':
                    $stageValue = json_decode((string)($stageRow[$column] ?? null), true);
                    $masterValue = json_decode((string)($masterRow[$column] ?? null), true);
                    $stageValue = is_array($stageValue) ? array_unique(array_map('strval', $stageValue)) : [];
                    $masterValue = is_array($masterValue) ? array_unique(array_map('strval', $masterValue)) : [];
                    if (empty($stageValue) && empty($masterValue)) {
                        break;
                    }
                    $similarity += count(array_intersect($stageValue, $masterValue)) / count(array_unique(array_merge($stageValue, $masterValue)));
                    $count++;
                    break;
                default:
                    $isStageEmpty = true;
                    $isMasterEmpty = true;
                    $isEqual = true;
                    foreach (array_unique([$column, $column . '1', 'reference_value', 'prefix_value']) as $key) {
                        $stageValue = $stageRow[$key] ?? null;
                        $masterValue = $masterRow[$key] ?? null;
                        $stageValue = $stageValue === '' ? null : $stageValue;
                        $masterValue = $masterValue === '' ? null : $masterValue;
                        $isStageEmpty = $isStageEmpty && $stageValue === null;
                        $isMasterEmpty = $isMasterEmpty && $masterValue === null;
                        $isEqual = $isEqual && ($stageValue === null ? $masterValue === null : $masterValue !== null && $stageValue == $masterValue);
                    }
                    if ($isStageEmpty && $isMasterEmpty) {
                        break;
                    }
                    $similarity += !$isStageEmpty && !$isMasterEmpty && $isEqual ? 1 : 0;
                    $count++;
            }
        }

        return $count > 0 ? $this->getWeight() * $similarity / $count : 0.0;
    }

    protected function getExcludedAttributeIds(): array
    {
        if (isset($this->excludedAttributeIds[$this->rule->id])) {
            return $this->excludedAttributeIds[$this->rule->id];
        }

        $matching = $this->rule->getMatching();
        if (empty($matching)) {
            return [];
        }

        $attributeIds = [];
        $collections = [$matching->get('matchingRules') ?? []];
        while (!empty($collections)) {
            $collection = array_pop($collections);
            for ($i = 0; $i < count($collection); $i++) {
                $rule = $collection[$i];
                $settings = $rule->getSubtype() ?? $rule;
                if ($rule->id !== $this->rule->id && !empty($settings->get('attributeId'))) {
                    $attributeIds[] = $settings->get('attributeId');
                }
                $collections[] = $rule->getNestedRules();
            }
        }

        return $this->excludedAttributeIds[$this->rule->id] = $attributeIds;
    }

    protected function getAttributeRepository(): AttributeRepository
    {
        return $this->getEntityManager()->getRepository('Attribute');
    }
}
