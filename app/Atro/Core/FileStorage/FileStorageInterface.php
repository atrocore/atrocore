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
use Atro\Entities\Storage;

interface FileStorageInterface
{
    public function scan(Storage $storage): void;

    public function delete(File $file): void;

    public function getLocalPath(File $file): string;

    public function getUrl(File $file): string;

    public function getThumbnailUrl(File $file, string $type): string;
}