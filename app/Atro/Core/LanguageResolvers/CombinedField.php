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

class CombinedField extends AbstractLanguageResolver
{
    private array $scopeCache = [];

    public function decoratesStoredTranslation(): bool
    {
        return true;
    }

    public function supports(TranslationKeyDTO $key): bool
    {
        if ($key->category !== 'fields' || $key->scope === 'Global') {
            return false;
        }

        return isset($this->buildScope($key->scope)['fields'][$key->name]);
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        return $this->buildScope($key->scope)['fields'][$key->name] ?? null;
    }

    public function getKeys(): iterable
    {
        foreach (array_keys($this->getMetadata()->get('entityDefs', [])) as $entity) {
            foreach (array_keys($this->buildScope($entity)['fields'] ?? []) as $field) {
                yield new TranslationKeyDTO($entity, 'fields', (string)$field);
            }
        }
    }

    private function buildScope(string $scope): array
    {
        if (isset($this->scopeCache[$scope])) {
            return $this->scopeCache[$scope];
        }

        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields'], []) as $field => $fieldDefs) {
            if (empty($fieldDefs['type']) || empty($fieldDefs['combinedField'])) {
                continue;
            }

            $mainField = $fieldDefs['mainField'] ?? $field;

            $fieldLabel = $this->language->getStoredTranslation(new TranslationKeyDTO($scope, 'fields', $mainField))
                ?? $this->language->getStoredTranslation(new TranslationKeyDTO('Global', 'fields', $mainField))
                ?? $mainField;

            if (!in_array($fieldDefs['type'], ['rangeInt', 'rangeFloat'], true)) {
                $mainFieldType = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $mainField, 'type']);

                $result['fields'][$mainField] = $fieldLabel . ' ' . $this->language->translate($mainFieldType . 'Part', 'labels', $scope);
                $result['fields']['combined' . ucfirst($mainField)] = $fieldLabel;
            }

            if (!empty($fieldDefs['measureId'])) {
                $result['fields'][$mainField . 'Unit'] = $fieldLabel . ' ' . $this->language->translate('unitPart', 'labels', $scope);
            }

            if (!empty($fieldDefs['prefixEnabled'])) {
                $result['fields'][$mainField . 'Prefix'] = $fieldLabel . ' ' . $this->language->translate('prefixPart', 'labels', $scope);
            }
        }

        return $this->scopeCache[$scope] = $result;
    }
}
