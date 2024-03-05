<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\FileStorage;

use Atro\Entities\File;

interface FileStorageInterface
{
    public function delete(File $file): string;

    public function getLocalPath(File $file): string;

    public function getUrl(File $file): string;

    public function getThumbnailUrl(File $file, string $type): string;
}