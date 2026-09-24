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

namespace Atro\Core\LanguageResolvers;

use Atro\DTOs\TranslationKeyDTO;

class RangeField extends AbstractLanguageResolver
{
    private const TYPES = ['rangeInt', 'rangeFloat'];

    public function supports(TranslationKeyDTO $key): bool
    {
        if ($key->category !== 'fields' || $key->scope === 'Global') {
            return false;
        }

        return $this->getMainField($key->scope, $key->name) !== null;
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        $mainField = $this->getMainField($key->scope, $key->name);
        if ($mainField === null) {
            return null;
        }

        $suffix = str_ends_with($key->name, 'From') ? 'From' : 'To';

        return $this->buildLabel($key->scope, $mainField, $suffix);
    }

    public function getKeys(): iterable
    {
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
            foreach ($entityDefs['fields'] ?? [] as $field => $fieldDefs) {
                if (!in_array($fieldDefs['type'] ?? null, self::TYPES, true)) {
                    continue;
                }

                foreach (['From', 'To'] as $suffix) {
                    yield new TranslationKeyDTO($entity, 'fields', $field . $suffix);
                }
            }
        }
    }

    private function buildLabel(string $scope, string $mainField, string $suffix): string
    {
        $fieldLabel = $this->language->getStoredTranslation(new TranslationKeyDTO($scope, 'fields', $mainField)) ?? $mainField;

        $label = $fieldLabel . ' ' . $this->language->translate($suffix, 'labels');

        $fieldDefs = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $mainField], []);
        if (!empty($fieldDefs['combinedField'])) {
            $fieldType = $fieldDefs['type'] === 'rangeInt' ? 'int' : 'float';
            $typeLabel = $this->language->getStoredTranslation(new TranslationKeyDTO('Global', 'labels', $fieldType . 'Part')) ?? "($fieldType)";
            $label .= ' ' . $typeLabel;
        }

        return $label;
    }

    private function getMainField(string $scope, string $field): ?string
    {
        foreach (['From', 'To'] as $suffix) {
            if (!str_ends_with($field, $suffix)) {
                continue;
            }

            $mainField = substr($field, 0, -strlen($suffix));
            if ($mainField === '') {
                continue;
            }

            $type = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $mainField, 'type']);
            if (in_array($type, self::TYPES, true)) {
                return $mainField;
            }
        }

        return null;
    }
}
