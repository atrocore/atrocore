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

class LabelKey extends AbstractLanguageResolver
{
    public function supports(TranslationKeyDTO $key): bool
    {
        return $key->category === 'fields'
            && $key->scope !== 'Global'
            && !empty($this->getMetadata()->get(['entityDefs', $key->scope, 'fields', $key->name, 'labelKey']));
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        $labelKey = $this->getMetadata()->get(['entityDefs', $key->scope, 'fields', $key->name, 'labelKey']);

        return $this->translateLabelKey($labelKey);
    }

    public function getKeys(): iterable
    {
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
            foreach ($entityDefs['fields'] ?? [] as $field => $fieldDefs) {
                if (!empty($fieldDefs['labelKey'])) {
                    yield new TranslationKeyDTO($entity, 'fields', $field);
                }
            }
        }
    }

    private function translateLabelKey(string $labelKey): ?string
    {
        $parts = explode('.', $labelKey);
        if (count($parts) !== 3) {
            return null;
        }

        return $this->language->findTranslation(new TranslationKeyDTO($parts[0], $parts[1], $parts[2]));
    }
}
