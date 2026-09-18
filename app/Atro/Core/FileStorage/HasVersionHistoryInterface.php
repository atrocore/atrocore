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
 * Optional capability for a storage that archives content versions itself, keyed by a
 * PIM-generated version id and backed by the Versioning module's own local version table
 * (which holds the version's metadata - name, who, when.
 */
interface HasVersionHistoryInterface
{
    public function createFileVersion(File $file, string $versionId): bool;

    /**
     * @param File $version the versioned File entity — never the live entity,
     *                      whose current field values may no longer match what was archived.
     */
    public function getFileVersionContents(File $version): string;

    public function deleteFileVersion(File $file, string $versionId): bool;
}
