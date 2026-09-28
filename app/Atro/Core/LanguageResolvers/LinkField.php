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

class LinkField extends AbstractLanguageResolver
{
    public function supports(TranslationKeyDTO $key): bool
    {
        return $key->category === 'fields'
            && $key->scope !== 'Global'
            && $this->getMetadata()->get(['entityDefs', $key->scope, 'fields', $key->name, 'type']) === 'link';
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        $globalField = new TranslationKeyDTO('Global', 'fields', $key->name);

        $entityType = $this->getMetadata()->get(['entityDefs', $key->scope, 'links', $key->name, 'entity']);
        if (empty($entityType)) {
            return $this->language->getStoredTranslationInCurrentLanguage($globalField);
        }

        $scopeName = new TranslationKeyDTO('Global', 'scopeNames', $entityType);

        $exact = $this->language->getStoredTranslationInCurrentLanguage($globalField)
            ?? $this->language->getStoredTranslationInCurrentLanguage($scopeName);

        if ($exact !== null) {
            return $exact;
        }

        if ($this->language->getStoredTranslation($key) !== null) {
            return null;
        }

        return $this->language->findTranslation($globalField) ?? $this->language->findTranslation($scopeName);
    }

    public function getKeys(): iterable
    {
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
            foreach ($entityDefs['fields'] ?? [] as $field => $fieldDefs) {
                if (($fieldDefs['type'] ?? null) === 'link') {
                    yield new TranslationKeyDTO($entity, 'fields', $field);
                }
            }
        }
    }
}
