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

class MasterRecordLink extends AbstractLanguageResolver
{
    public function supports(TranslationKeyDTO $key): bool
    {
        if ($key->category !== 'fields' || $key->scope === 'Global') {
            return false;
        }

        return $this->getLinkedEntity($key->scope, $key->name) !== null;
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        $linkedEntity = $this->getLinkedEntity($key->scope, $key->name);
        if ($linkedEntity === null) {
            return null;
        }

        return $this->language->translate('contributorRecords', 'fields')
            . ' (' . $this->language->translate($linkedEntity, 'scopeNames') . ')';
    }

    public function getKeys(): iterable
    {
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
            foreach ($entityDefs['links'] ?? [] as $link => $linkDefs) {
                if (($linkDefs['foreign'] ?? null) !== 'masterRecord' || empty($linkDefs['entity'])) {
                    continue;
                }
                if (empty($entityDefs['fields'][$link])) {
                    continue;
                }

                yield new TranslationKeyDTO($entity, 'fields', $link);
            }
        }
    }

    private function getLinkedEntity(string $scope, string $field): ?string
    {
        if (empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', $field, 'type']))) {
            return null;
        }

        $linkDefs = $this->getMetadata()->get(['entityDefs', $scope, 'links', $field], []);

        if (($linkDefs['foreign'] ?? null) !== 'masterRecord' || empty($linkDefs['entity'])) {
            return null;
        }

        return $linkDefs['entity'];
    }
}
