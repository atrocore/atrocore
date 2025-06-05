<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class FormatField extends AbstractTwigFunction
{

    public function __construct(private readonly Language $language)
    {
    }

    public function run(Entity $entity, string $field): string|null
    {
        if ($field == 'id') {
            return $entity->id;
        }

        if (empty($metadata = $entity->entityDefs['fields'][$field] ?? null)) {
            return null;
        }

        $value = $entity->get($field);

        if (in_array($metadata['type'], ['rangeInt', 'rangeFloat'])) {
            $value = ($entity->get($field . 'From') ?? 'Null') . ' – ' . ($entity->get($field . 'To') ?? 'Null');
        } else if ($metadata['type'] == 'enum') {
            $value = $this->language->translateOption($value, $field, $entity->getEntityType());
        } else if ($metadata['type'] == 'multiEnum' && is_array($value)) {
            $value = array_map(fn($i) => $this->language->translateOption($i, $field, $entity->getEntityType()), $value);
        } else if ($metadata['type'] == 'bool' && is_bool($value)) {
            $value = $value ? 'True' : 'False';
        } else if (in_array($metadata['type'], ['link', 'linkMultiple', 'extensibleEnum', 'extensibleMultiEnum'])) {
            list($attributeValue, $attributeValueName) = match ($metadata['type']) {
                'extensibleEnum'      => [$entity->get($field), $entity->get($field . 'Name')],
                'extensibleMultiEnum' => [$entity->get($field), $entity->get($field . 'Names')],
                'link'                => [$entity->get($field . 'Id'), $entity->get($field . 'Name')],
                'linkMultiple'        => [$entity->get($field . 'Ids'), $entity->get($field . 'Names')]
            };

            if (is_array($attributeValue)) {
                $value = array_map(fn($v) => $attributeValueName->{$v} ?? $attributeValueName[$v] ?? $v, $attributeValue);
            } else {
                $value = $attributeValueName ?: $attributeValue;
            }
        } else if ($metadata['type'] == 'markdown') {
            $value = (new \Parsedown())->parse($value);
        }

        if ($value && $entity->get($field . 'UnitName')) {
            $value .= ' ' . $entity->get($field . 'UnitName');
        } else if ($value && $entity->get($field . 'Unit')) {
            $value .= ' ' . $entity->get($field . 'Unit');
        }

        if ($value instanceof EntityCollection) {
            $value = iterator_to_array($value);
        }

        if (is_array($value)) {
            $value = implode(', ', array_map(fn($i) => (string)$i, $value)) ?: null;
        }

        return $value;
    }
}