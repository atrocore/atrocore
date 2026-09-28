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

class Association extends AbstractLanguageResolver
{
    private ?array $associateEntities = null;
    private array $scopeCache = [];

    public function supports(TranslationKeyDTO $key): bool
    {
        if ($key->scope === 'Global') {
            return $key->category === 'scopeNames'
                && !empty($this->getMetadata()->get(['scopes', $key->name, 'associatesForEntity']));
        }

        return isset($this->buildScope($key->scope)[$key->category][$key->name]);
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        if ($key->scope === 'Global') {
            $associateEntity = $this->getMetadata()->get(['scopes', $key->name, 'associatesForEntity']);
            if (empty($associateEntity)) {
                return null;
            }

            return $this->language->translate('Associated', 'labels')
                . ' ' . $this->language->translate($associateEntity, 'scopeNames');
        }

        return $this->buildScope($key->scope)[$key->category][$key->name] ?? null;
    }

    public function getKeys(): iterable
    {
        foreach ($this->getMetadata()->get('scopes', []) as $scope => $scopeDefs) {
            foreach ($this->buildScope($scope) as $category => $items) {
                foreach (array_keys($items) as $name) {
                    yield new TranslationKeyDTO($scope, $category, (string)$name);
                }
            }

            if (!empty($scopeDefs['associatesForEntity'])) {
                yield new TranslationKeyDTO('Global', 'scopeNames', $scope);
            }
        }
    }

    private function buildScope(string $scope): array
    {
        if (isset($this->scopeCache[$scope])) {
            return $this->scopeCache[$scope];
        }

        $result = [];

        $associateEntity = $this->getMetadata()->get(['scopes', $scope, 'associatesForEntity']);
        if (!empty($associateEntity) && !empty($this->getMetadata()->get(['entityDefs', $scope, 'fields']))) {
            $result['fields']['associatingItem'] = $this->language->translate('associatingItem', 'labels');
            $result['fields']['associatedItem'] = $this->language->translate('associatedItem', 'labels');
            $result['fields']['associatedItems'] = $this->language->translate('associatedItems', 'labels');
            $result['tooltips']['association'] = $this->language->translate('associationTooltip', 'labels');
        }

        if (isset($this->getAssociateEntities()[$scope])) {
            $relationLabel = $this->language->translate('Relation', 'fields');

            $result['fields']['associatedItems'] = $this->language->translate('associatedItems', 'labels');
            $result['fields']['associatingItems'] = $this->language->translate('associatingItems', 'labels');
            $result['fields']['associatedItemRelations'] = $result['fields']['associatedItems'] . ' (' . $relationLabel . ')';
            $result['fields']['associatingItemRelations'] = $result['fields']['associatingItems'] . ' (' . $relationLabel . ')';
        }

        return $this->scopeCache[$scope] = $result;
    }

    private function getAssociateEntities(): array
    {
        if ($this->associateEntities === null) {
            $this->associateEntities = [];
            foreach ($this->getMetadata()->get('scopes', []) as $scopeDefs) {
                if (!empty($scopeDefs['associatesForEntity'])) {
                    $this->associateEntities[$scopeDefs['associatesForEntity']] = true;
                }
            }
        }

        return $this->associateEntities;
    }
}
