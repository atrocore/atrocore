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

/**
 * A field of a subtype on its supertype, e.g. "ExportFeed.fields.ExportFeedTypeSimple_fileNameMask", is labelled by the
 * type of the subtype and the label of the field: "Simple: File Name Mask". Its options are the ones of the field.
 */
class SubtypeField extends AbstractLanguageResolver
{
    public function supports(TranslationKeyDTO $key): bool
    {
        if ($key->category === 'fields') {
            return $this->getSubtypeField($key) !== null;
        }

        return $key->category === 'options' && $key->option !== null && $this->getSubtypeField($key) !== null;
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        $subtypeField = $this->getSubtypeField($key);
        if ($subtypeField === null) {
            return null;
        }

        $subtype = $subtypeField['entityName'];
        $field = $subtypeField['field'];

        if ($key->category === 'options') {
            return $this->language->findTranslation(new TranslationKeyDTO($subtype, 'options', $field, $key->option));
        }

        $type = (string)$this->getMetadata()->get(['entityDefs', $subtype, 'fields', 'type', 'default']);
        $typeLabel = $this->language->findTranslation(new TranslationKeyDTO($key->scope, 'options', 'type', $type)) ?? $type;
        $fieldLabel = $this->language->findTranslation(new TranslationKeyDTO($subtype, 'fields', $field)) ?? $field;

        return "$typeLabel: $fieldLabel";
    }

    public function getKeys(): iterable
    {
        foreach ($this->getMetadata()->get('scopes', []) as $subtype => $scopeDefs) {
            $supertype = $scopeDefs['supertypeId'] ?? null;
            if (empty($supertype) || empty($this->getMetadata()->get(['scopes', $supertype, 'hasSubtypeFields']))) {
                continue;
            }

            foreach ($this->getMetadata()->get(['entityDefs', $subtype, 'fields'], []) as $field => $fieldDefs) {
                $name = "{$subtype}_{$field}";
                $key = new TranslationKeyDTO($supertype, 'fields', $name);
                if ($this->getSubtypeField($key) === null) {
                    continue;
                }

                yield $key;

                foreach ($fieldDefs['options'] ?? [] as $option) {
                    if (is_string($option) && $option !== '') {
                        yield new TranslationKeyDTO($supertype, 'options', $name, $option);
                    }
                }
            }
        }
    }

    /**
     * The field of the subtype the key is about. A name of an attribute of the field is not a label of its own.
     */
    private function getSubtypeField(TranslationKeyDTO $key): ?array
    {
        if ($key->scope === 'Global' || !str_contains($key->name, '_')) {
            return null;
        }

        $subtypeField = $this->getMetadata()->getSubtypeField($key->scope, $key->name);
        if ($subtypeField === null || $subtypeField['field'] !== $subtypeField['attribute']) {
            return null;
        }

        return $subtypeField;
    }
}
