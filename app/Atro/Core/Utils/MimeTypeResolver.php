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

namespace Atro\Core\Utils;

use Symfony\Component\Mime\MimeTypes;

/**
 * Resolves MIME from content and the extension.
 *
 * Only text content is refined by the extension (libmagic can tell it is text, not which format),
 * and only into another text type. Any other result of the content, inconclusive ones included, stays as it is.
 * Active MIME types are never inferred from the filename.
 */
class MimeTypeResolver
{
    public const DEFAULT_TYPE = 'application/octet-stream';

    /**
     * MIME types that must not be inferred from the extension: anything a browser renders or executes.
     * A pattern, not a list, so the aliases of the extension map (text/jscript, image/svg, *+xml) are covered too.
     * A type detected from the content may still be returned.
     */
    public const ACTIVE_TYPE_PATTERN = '#html|xml|svg|javascript|ecmascript|jscript#';

    private ?\finfo $fileInfo = null;

    private ?MimeTypes $mimeTypes = null;

    public function resolveByPath(string $path, string $filename): string
    {
        $contentMimeType = @$this->getFileInfo()->file($path);

        return $this->resolve(is_string($contentMimeType) ? $contentMimeType : null, $filename);
    }

    public function resolveByContents(string $contents, string $filename): string
    {
        $contentMimeType = @$this->getFileInfo()->buffer($contents);

        return $this->resolve(is_string($contentMimeType) ? $contentMimeType : null, $filename);
    }

    protected function resolve(?string $contentMimeType, string $filename): string
    {
        // A specific binary type is reliable as it is. An inconclusive one (octet-stream, empty) is kept too:
        // the extension is the only source there, and it must not be able to name unknown bytes an image or a program.
        if ($contentMimeType === null || !$this->isText($contentMimeType)) {
            return $contentMimeType ?? self::DEFAULT_TYPE;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $extensionMimeTypes = array_filter(
            $this->getTypesByExtension($extension),
            fn(string $type) => $this->isText($type) && !$this->isActive($type)
        );

        // The content agrees with the extension.
        if (in_array($contentMimeType, $extensionMimeTypes, true)) {
            return $contentMimeType;
        }

        // The list starts with the preferred type, the rest is alphabetical - so the first text one, not just the first one.
        return reset($extensionMimeTypes) ?: $contentMimeType;
    }

    protected function isActive(string $type): bool
    {
        return preg_match(self::ACTIVE_TYPE_PATTERN, $type) === 1;
    }

    /**
     * Types eligible for text-based extension refinement.
     * This is a policy list, not an exhaustive classification of textual MIME.
     */
    protected function isText(string $type): bool
    {
        return str_starts_with($type, 'text/') || in_array($type, ['application/json', 'application/csv'], true);
    }

    protected function getTypesByExtension(string $extension): array
    {
        return $extension === '' ? [] : $this->getMimeTypes()->getMimeTypes($extension);
    }

    private function getMimeTypes(): MimeTypes
    {
        return $this->mimeTypes ??= new MimeTypes();
    }

    private function getFileInfo(): \finfo
    {
        return $this->fileInfo ??= new \finfo(FILEINFO_MIME_TYPE);
    }
}
