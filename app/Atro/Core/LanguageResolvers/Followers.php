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

class Followers extends AbstractLanguageResolver
{
    private const SCOPE_PREFIX = 'UserFollowed';
    private const LINK_SCOPE = 'User';
    private const SCOPE_NAME_CATEGORIES = ['scopeNames', 'scopeNamesPlural'];

    public function supports(TranslationKeyDTO $key): bool
    {
        if ($key->scope === 'Global') {
            return in_array($key->category, self::SCOPE_NAME_CATEGORIES, true)
                && str_starts_with($key->name, self::SCOPE_PREFIX)
                && !empty($this->getMetadata()->get(['scopes', $key->name]));
        }

        return $key->category === 'fields'
            && $key->scope === self::LINK_SCOPE
            && $this->getLinkedEntity($key->name) !== null;
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        if ($key->scope === 'Global') {
            if ($this->language->getStoredTranslationInCurrentLanguage($key) !== null) {
                return null;
            }

            $relatedScope = substr($key->name, strlen(self::SCOPE_PREFIX));

            return $this->language->translate(self::SCOPE_PREFIX, 'labels')
                . ' ' . $this->language->translate($relatedScope, $key->category);
        }

        $entity = $this->getLinkedEntity($key->name);
        if ($entity === null) {
            return null;
        }

        $entityLabel = $this->language->findTranslation(new TranslationKeyDTO('Global', 'scopeNamesPlural', $entity));
        if ($entityLabel === null) {
            return null;
        }

        return $entityLabel . ' (' . $this->language->translate('Followed', 'labels') . ')';
    }

    public function getKeys(): iterable
    {
        foreach (array_keys($this->getMetadata()->get('scopes', [])) as $scope) {
            if (!str_starts_with($scope, self::SCOPE_PREFIX)) {
                continue;
            }

            foreach (self::SCOPE_NAME_CATEGORIES as $category) {
                yield new TranslationKeyDTO('Global', $category, $scope);
            }
        }

        foreach (array_keys($this->getMetadata()->get(['entityDefs', self::LINK_SCOPE, 'links'], [])) as $link) {
            if ($this->getLinkedEntity((string)$link) !== null) {
                yield new TranslationKeyDTO(self::LINK_SCOPE, 'fields', (string)$link);
            }
        }
    }

    private function getLinkedEntity(string $link): ?string
    {
        $linkDefs = $this->getMetadata()->get(['entityDefs', self::LINK_SCOPE, 'links', $link], []);

        if (($linkDefs['foreign'] ?? null) !== 'followers' || empty($linkDefs['entity'])) {
            return null;
        }

        return $linkDefs['entity'];
    }
}
