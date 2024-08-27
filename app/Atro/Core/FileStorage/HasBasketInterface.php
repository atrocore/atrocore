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
use Atro\Entities\Folder;

interface HasBasketInterface
{
    public function deleteFile(File $file): bool;

    public function restoreFile(File $file): bool;

    public function deleteFolder(Folder $folder): bool;

    public function restoreFolder(Folder $folder): bool;
}