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
use Atro\Entities\Storage;

interface FileStorageInterface
{
    public function scan(Storage $storage): void;

    public function create(File $file): bool;

    public function rename(File $file): bool;

    public function delete(File $file): bool;

    public function getUrl(File $file): string;

    public function getContents(File $file): string;
}