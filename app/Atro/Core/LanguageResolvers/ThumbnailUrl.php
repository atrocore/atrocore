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

use Atro\Entities\File;

class ThumbnailUrl extends AbstractLanguageResolver
{
    public function supports(TranslationKeyDTO $key): bool
    {
        return $key->scope === 'File'
            && $key->category === 'fields'
            && $this->getSize($key->name) !== null;
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        $size = $this->getSize($key->name);

        return $size === null ? null : sprintf($this->getLabel(), $size);
    }

    public function getKeys(): iterable
    {
        foreach (array_keys($this->getMetadata()->get(['app', 'thumbnailTypes'], [])) as $size) {
            yield new TranslationKeyDTO('File', 'fields', File::prepareThumbnailUrlFieldName((string)$size));
        }
    }

    private function getLabel(): string
    {
        return $this->language->getStoredTranslation(new TranslationKeyDTO('File', 'labels', 'thumbnailUrl')) ?? 'Thumbnail URL (%s)';
    }

    private function getSize(string $field): ?string
    {
        foreach ($this->getMetadata()->get(['app', 'thumbnailTypes'], []) as $size => $params) {
            if (File::prepareThumbnailUrlFieldName((string)$size) === $field) {
                return (string)$size;
            }
        }

        return null;
    }
}
