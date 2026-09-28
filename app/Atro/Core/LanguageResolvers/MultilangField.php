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

use Atro\Core\Utils\Language;
use Atro\DTOs\TranslationKeyDTO;

class MultilangField extends AbstractLanguageResolver
{
    private const CATEGORIES = ['fields', 'tooltips'];

    private array $languageInstances = [];

    public function decoratesStoredTranslation(): bool
    {
        return true;
    }

    public function supports(TranslationKeyDTO $key): bool
    {
        if (!in_array($key->category, self::CATEGORIES, true) || empty($this->getContentLanguages())) {
            return false;
        }

        if ($this->getBaseField($key) !== null) {
            return true;
        }

        return $key->category === 'fields'
            && $this->isDecoratedBaseField($key->scope, $key->name);
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        $base = $this->getBaseField($key);

        if ($base === null) {
            if ($key->category !== 'fields' || !$this->isDecoratedBaseField($key->scope, $key->name)) {
                return null;
            }

            $value = $this->language->findTranslation($key, $this);

            return $value === null ? null : $value . ' / ' . $this->getMainLanguageName();
        }

        [$field, $code] = $base;

        $baseKey = new TranslationKeyDTO($key->scope, $key->category, $field);

        $value = $this->language->getStoredTranslation($baseKey) ?? $this->language->findTranslation($baseKey, $this);
        if ($value === null) {
            return null;
        }

        return $this->buildSuffixedLabel($key->scope, $key->category, $field, $value, $code);
    }

    public function getKeys(): iterable
    {
        $languages = $this->getContentLanguages();
        if (empty($languages)) {
            return;
        }

        $stored = $this->language->getStoredTranslations();

        foreach (self::CATEGORIES as $category) {
            foreach (array_keys($stored['Global'][$category] ?? []) as $field) {
                foreach (array_keys($languages) as $code) {
                    yield new TranslationKeyDTO('Global', $category, $field . $this->getLanguageSuffix($code));
                }
            }
        }

        foreach ($this->getMetadata()->get('entityDefs', []) as $scope => $entityDefs) {
            if ($scope === 'Global') {
                continue;
            }

            foreach ($entityDefs['fields'] ?? [] as $field => $fieldDefs) {
                if (empty($fieldDefs['isMultilang'])) {
                    continue;
                }

                if ($this->isDecoratedBaseField($scope, $field)) {
                    yield new TranslationKeyDTO($scope, 'fields', $field);
                }

                foreach (self::CATEGORIES as $category) {
                    foreach (array_keys($languages) as $code) {
                        yield new TranslationKeyDTO($scope, $category, $field . $this->getLanguageSuffix($code));
                    }
                }
            }
        }
    }

    private function buildSuffixedLabel(string $scope, string $category, string $field, string $value, string $code): string
    {
        if ($category !== 'fields' || $code === $this->language->getLanguage()) {
            return $value;
        }

        if ($this->displayLabelsInContentLanguage()) {
            $contentValue = $this->getLabelInLanguage($code, $scope, $field);
            if (!empty($contentValue)) {
                return $contentValue;
            }
        }

        return $value . ' / ' . $this->getContentLanguages()[$code];
    }

    private function isDecoratedBaseField(string $scope, string $field): bool
    {
        if ($scope === 'Global' || !isset($this->getContentLanguages()[$this->language->getLanguage()])) {
            return false;
        }

        if (empty($this->getMainLanguageName()) || $this->displayLabelsInContentLanguage()) {
            return false;
        }

        return !empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', $field, 'isMultilang']));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function getBaseField(TranslationKeyDTO $key): ?array
    {
        foreach ($this->getContentLanguages() as $code => $name) {
            $suffix = $this->getLanguageSuffix($code);
            if (!str_ends_with($key->name, $suffix)) {
                continue;
            }

            $field = substr($key->name, 0, -strlen($suffix));
            if ($field === '') {
                continue;
            }

            if ($key->scope === 'Global') {
                return [$field, $code];
            }

            if (!empty($this->getMetadata()->get(['entityDefs', $key->scope, 'fields', $field, 'isMultilang']))) {
                return [$field, $code];
            }
        }

        return null;
    }

    private function displayLabelsInContentLanguage(): bool
    {
        $locales = $this->getConfig()->get('locales') ?? [];

        return !empty($locales[$this->language->getLocaleId()]['displayLabelsInContentLanguage']);
    }

    private function getLabelInLanguage(string $code, string $scope, string $field): ?string
    {
        if (!isset($this->languageInstances[$code])) {
            $instance = new Language($this->container, $this->language->getLocaleId());
            $instance->setLanguage($code);
            $this->languageInstances[$code] = $instance;
        }

        return $this->languageInstances[$code]->getStoredTranslation(new TranslationKeyDTO($scope, 'fields', $field))
            ?? $this->languageInstances[$code]->getStoredTranslation(new TranslationKeyDTO('Global', 'fields', $field));
    }

}
