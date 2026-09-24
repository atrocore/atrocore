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

class NameField extends AbstractLanguageResolver
{
    public function supports(TranslationKeyDTO $key): bool
    {
        return $key->name === 'name'
            && $key->category === 'fields'
            && $key->scope !== 'Global'
            && !empty($this->getMetadata()->get(['entityDefs', $key->scope, 'fields', 'name']));
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        return $this->language->getStoredTranslationInCurrentLanguage(new TranslationKeyDTO('Global', 'fields', 'name')) ?? 'Name';
    }

    public function getKeys(): iterable
    {
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
            if ($entity !== 'Global' && !empty($entityDefs['fields']['name'])) {
                yield new TranslationKeyDTO($entity, 'fields', 'name');
            }
        }
    }
}
