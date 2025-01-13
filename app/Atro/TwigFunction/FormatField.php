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
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class FormatField extends AbstractTwigFunction
{

    public function __construct(private readonly Metadata $metadata, private readonly Language $language)
    {
    }

    public function run(Entity $entity, string $field): string|null
    {
        if ($field == 'id') {
            return $entity->id;
        }

        if (empty($metadata = $this->metadata->get(['entityDefs', $entity->getEntityType(), 'fields', $field], []))) {
            return null;
        }

        $value = $entity->get($field);

        if (in_array($metadata['type'], ['rangeInt', 'rangeFloat'])) {
            $value = ($entity->get($field . 'From') ?? 'Null') . ' – ' . ($entity->get($field . 'To') ?? 'Null');
        } else if ($metadata['type'] == 'enum') {
            $value = $this->language->translateOption($value, $field, $entity->getEntityType());
        } else if ($metadata['type'] == 'multiEnum' && is_array($value)) {
            $value = array_map(fn($i) => $this->language->translateOption($i, $field, $entity->getEntityType()), $value);
        }

        if ($value && !empty($metadata['measureId']) && in_array($metadata['type'], ['int', 'float', 'rangeInt', 'rangeFloat'])) {
            $value .= ' ' . $entity->get($field . 'UnitName');
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