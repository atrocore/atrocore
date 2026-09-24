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

class EntityFieldScriptPreview extends AbstractLanguageResolver
{
    private const SCOPE = 'EntityField';
    private const FIELDS = ['script', 'preview'];

    public function supports(TranslationKeyDTO $key): bool
    {
        if ($key->scope !== self::SCOPE || $key->category !== 'fields') {
            return false;
        }

        return $this->getBaseField($key->name) !== null;
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        $base = $this->getBaseField($key->name);
        if ($base === null) {
            return null;
        }

        [$field, $code] = $base;

        return $this->buildLabel($field) . ' / ' . $this->getContentLanguages()[$code];
    }

    public function getKeys(): iterable
    {
        foreach (self::FIELDS as $field) {
            if (!empty($this->getMetadata()->get(['entityDefs', self::SCOPE, 'fields', $field, 'isMultilang']))) {
                continue;
            }

            foreach (array_keys($this->getContentLanguages()) as $code) {
                yield new TranslationKeyDTO(self::SCOPE, 'fields', $field . $this->getLanguageSuffix($code));
            }
        }
    }

    private function buildLabel(string $field): string
    {
        return $this->language->getStoredTranslation(new TranslationKeyDTO(self::SCOPE, 'fields', $field)) ?? $field;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function getBaseField(string $name): ?array
    {
        foreach (self::FIELDS as $field) {
            if (!empty($this->getMetadata()->get(['entityDefs', self::SCOPE, 'fields', $field, 'isMultilang']))) {
                continue;
            }

            foreach ($this->getContentLanguages() as $code => $languageName) {
                if ($name === $field . $this->getLanguageSuffix($code)) {
                    return [$field, $code];
                }
            }
        }

        return null;
    }

}
