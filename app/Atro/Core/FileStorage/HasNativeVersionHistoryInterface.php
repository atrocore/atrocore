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

namespace Atro\Core\FileStorage;

use Atro\Entities\File;

/**
 * Optional capability for a storage whose remote backend already maintains its own version
 * history, bypassing the local version table entirely.
 */
interface HasNativeVersionHistoryInterface
{
    /**
     * @return array<int, array{id: string, name: string, createdAt: ?string}>
     */
    public function getFileVersions(File $file): array;

    public function getFileVersionContents(File $file, string $versionId): string;

    /**
     * Forces a new entry in the version history with no content change - pushes the file's
     * current bytes back through reupload(). Mutates $file like reupload() does; caller persists.
     */
    public function createVersion(File $file): bool;

    /**
     * Stamps $file with $versionId and overwrites its own attributes (name, mimeType, fileSize,
     * width, height...) to match that historical version instead of the live one.
     */
    public function populateFileVersion(File $file, string $versionId): void;
}
