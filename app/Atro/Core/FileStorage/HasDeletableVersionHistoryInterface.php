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
 * Optional capability, on top of HasNativeVersionHistoryInterface, for a storage whose remote
 * backend can also delete one specific version out of its own history
 */
interface HasDeletableVersionHistoryInterface
{
    public function deleteFileVersion(File $file, string $versionId): bool;
}
