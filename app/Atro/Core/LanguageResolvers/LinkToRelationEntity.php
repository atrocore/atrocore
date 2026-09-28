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

class LinkToRelationEntity extends AbstractLanguageResolver
{
    public function supports(TranslationKeyDTO $key): bool
    {
        return $key->category === 'fields'
            && $key->scope !== 'Global'
            && !empty($this->getMetadata()->get(['entityDefs', $key->scope, 'fields', $key->name, 'linkToRelationEntity']));
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        $relationEntity = $this->getMetadata()->get(['entityDefs', $key->scope, 'links', $key->name, 'entity']);
        if (empty($relationEntity)) {
            return null;
        }

        $linkToRelationEntity = $this->getMetadata()->get(['entityDefs', $key->scope, 'fields', $key->name, 'linkToRelationEntity']);

        return $this->language->translate($linkToRelationEntity, 'scopeNamesPlural')
            . ' (' . $this->language->translate($relationEntity, 'scopeNames') . ')';
    }

    public function getKeys(): iterable
    {
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
            foreach ($entityDefs['fields'] ?? [] as $field => $fieldDefs) {
                if (!empty($fieldDefs['linkToRelationEntity'])) {
                    yield new TranslationKeyDTO($entity, 'fields', $field);
                }
            }
        }
    }
}
