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

use Espo\ORM\Entity;

class FieldsSimilar extends AbstractMatchingRule
{
    protected const array SYSTEM_FIELDS = [
        'createdAt',
        'modifiedAt',
        'createdBy',
        'modifiedBy',
        'ownerUser',
        'assignedUser',
    ];

    protected const array SIMILARITY_TYPES = [
        'varchar'             => 'text',
        'text'                => 'text',
        'wysiwyg'             => 'text',
        'markdown'            => 'text',
        'url'                 => 'text',
        'email'               => 'text',
        'array'               => 'list',
        'multiEnum'           => 'list',
    ];

    protected array $fieldTypes = [];

    public static function getSupportedFieldTypes(): array
    {
        return [];
    }

    public function getComparedFields(): array
    {
        return array_keys($this->getFieldTypes());
    }

    public function match(Entity $stageEntity, array $masterEntityData): float
    {
        $similarity = 0.0;
        $count = 0;

        foreach ($this->getFieldTypes() as $field => $type) {
            $key = in_array($type, ['link', 'file']) ? $field . 'Id' : $field;
            $stageValue = $stageEntity->get($key);
            $masterValue = $masterEntityData[$key] ?? null;

            switch (self::SIMILARITY_TYPES[$type] ?? null) {
                case 'text':
                    $stageTrigrams = $this->getTrigrams((string)$stageValue, $type);
                    $masterTrigrams = $this->getTrigrams((string)$masterValue, $type);
                    if (empty($stageTrigrams) && empty($masterTrigrams)) {
                        continue 2;
                    }
                    $similarity += 2 * count(array_intersect_key($stageTrigrams, $masterTrigrams)) / (count($stageTrigrams) + count($masterTrigrams));
                    break;
                case 'list':
                    if (is_string($masterValue)) {
                        $masterValue = json_decode($masterValue, true);
                    }
                    $stageValue = is_array($stageValue) ? array_unique(array_map('strval', $stageValue)) : [];
                    $masterValue = is_array($masterValue) ? array_unique(array_map('strval', $masterValue)) : [];
                    if (empty($stageValue) && empty($masterValue)) {
                        continue 2;
                    }
                    $similarity += count(array_intersect($stageValue, $masterValue)) / count(array_unique(array_merge($stageValue, $masterValue)));
                    break;
                default:
                    $isStageEmpty = $stageValue === null || $stageValue === '';
                    $isMasterEmpty = $masterValue === null || $masterValue === '';
                    if ($isStageEmpty && $isMasterEmpty) {
                        continue 2;
                    }
                    $similarity += !$isStageEmpty && !$isMasterEmpty && $stageValue == $masterValue ? 1 : 0;
            }
            $count++;
        }

        return $count > 0 ? $this->getWeight() * $similarity / $count : 0.0;
    }

    protected function getFieldTypes(): array
    {
        if (isset($this->fieldTypes[$this->rule->id])) {
            return $this->fieldTypes[$this->rule->id];
        }

        $matching = $this->rule->getMatching();
        if (empty($matching)) {
            return [];
        }

        $excludedFields = self::SYSTEM_FIELDS;
        foreach ([$matching->get('entity'), $matching->get('masterEntity')] as $entityName) {
            $excludedFields = array_merge($excludedFields, $this->getMetadata()->get("scopes.{$entityName}.nonComparableFields") ?? []);
        }

        $collections = [$matching->get('matchingRules') ?? []];
        while (!empty($collections)) {
            $collection = array_pop($collections);
            for ($i = 0; $i < count($collection); $i++) {
                $rule = $collection[$i];
                $settings = $rule->getSubtype() ?? $rule;
                if ($rule->id !== $this->rule->id && empty($settings->get('attributeId')) && !empty($settings->get('field'))) {
                    $excludedFields[] = $settings->get('field');
                }
                $collections[] = $rule->getNestedRules();
            }
        }

        $fieldTypes = [];
        foreach ($this->getMetadata()->get("entityDefs.{$matching->get('masterEntity')}.fields") ?? [] as $field => $defs) {
            $type = $defs['type'] ?? null;

            if (
                in_array($field, $excludedFields)
                || in_array($type, ['linkMultiple', 'linkParent', 'jsonObject', 'password'])
                || !empty($defs['notStorable'])
                || !empty($defs['disabled'])
                || !empty($defs['emHidden'])
                || !empty($defs['autoincrement'])
                || !empty($defs['dataLineage'])
                || $this->getMetadata()->get("entityDefs.{$matching->get('entity')}.fields.{$field}.type") !== $type
            ) {
                continue;
            }

            $fieldTypes[$field] = $type;
        }

        return $this->fieldTypes[$this->rule->id] = $fieldTypes;
    }

    protected function getTrigrams(string $value, string $type): array
    {
        if ($type === 'wysiwyg') {
            $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (class_exists('Normalizer')) {
            $value = \Normalizer::normalize($value) ?: $value;
        }

        $trigrams = [];
        foreach (preg_split('/[^\p{L}\p{M}\p{N}]+/u', mb_convert_case($value, MB_CASE_FOLD), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $chars = mb_str_split('  ' . $word . ' ');
            for ($i = 0; $i < count($chars) - 2; $i++) {
                $trigrams[$chars[$i] . $chars[$i + 1] . $chars[$i + 2]] = true;
            }
        }

        return $trigrams;
    }
}
