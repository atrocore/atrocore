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

class PersonalDataField extends AbstractLanguageResolver
{
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

        $fieldLabel = $this->language->translate($mainField, 'fields', $key->scope);

        return sprintf($this->language->translate('hasPersonalData', 'labels', $key->scope), $fieldLabel);
    }

    public function getKeys(): iterable
    {
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
            if (empty($this->getMetadata()->get(['scopes', $entity, 'containsPersonalData']))) {
                continue;
            }

            foreach ($entityDefs['fields'] ?? [] as $field => $fieldDefs) {
                if (empty($fieldDefs['personalData']) || empty($fieldDefs['notInEveryRecord'])) {
                    continue;
                }

                yield new TranslationKeyDTO($entity, 'fields', $field . 'HasPd');
            }
        }
    }

    private function getMainField(string $scope, string $field): ?string
    {
        $mainField = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $field, 'personalField']);
        if (empty($mainField)) {
            return null;
        }

        $fieldDefs = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $mainField], []);
        if (empty($fieldDefs['personalData']) || empty($fieldDefs['notInEveryRecord'])) {
            return null;
        }

        if (empty($this->getMetadata()->get(['scopes', $scope, 'containsPersonalData']))) {
            return null;
        }

        return $mainField;
    }
}
